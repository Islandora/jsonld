<?php

namespace Drupal\jsonld\Normalizer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\hal\LinkManager\LinkManagerInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

use Drupal\rdf\Entity\RdfMapping;
use ML\JsonLD\JsonLD;

/**
 * Converts the Drupal entity object structure to a JSON-LD array structure.
 */
class ContentEntityNormalizer extends NormalizerBase {

  const NORMALIZE_ALTER_HOOK = "jsonld_alter_normalized_array";

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Entity\ContentEntityInterface';

  /**
   * The hypermedia link manager.
   *
   * @var \Drupal\hal\LinkManager\LinkManagerInterface
   */
  protected $linkManager;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\hal\LinkManager\LinkManagerInterface $link_manager
   *   The hypermedia link manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(LinkManagerInterface $link_manager, EntityTypeManagerInterface $entity_manager, ModuleHandlerInterface $module_handler) {

    $this->linkManager = $link_manager;
    $this->entityManager = $entity_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {

    // We need to make sure that this only runs for JSON-LD.
    // @TODO check $format before going RDF crazy
    $normalized = [];

    if (isset($context['depth'])) {
      $context['depth'] += 1;
    }

    $context += [
      'account' => NULL,
      'included_fields' => NULL,
      'needs_jsonldcontext' => FALSE,
      'embedded' => FALSE,
      'namespaces' => rdf_get_namespaces(),
      'depth' => 0,
    ];

    if ($context['needs_jsonldcontext']) {
      $normalized['@context'] = $context['namespaces'];
    }
    // Let's see if this content entity has
    // rdf mapping associated to the bundle.
    $rdf_mappings = rdf_get_mapping($entity->getEntityTypeId(), $entity->bundle());
    $bundle_rdf_mappings = $rdf_mappings->getPreparedBundleMapping();

    // In Drupal space, the entity type URL.
    $drupal_entity_type = $this->linkManager->getTypeUri($entity->getEntityTypeId(), $entity->bundle(), $context);

    // Extract rdf:types.
    $hasTypes = empty($bundle_rdf_mappings['types']);
    $types = $hasTypes ? $drupal_entity_type : $bundle_rdf_mappings['types'];

    // If there's no context and the types are not drupal
    // entity types, we need full predicates,
    // not shortened ones. So we replace them in place.
    if ($context['needs_jsonldcontext'] === FALSE && is_array($types)) {
      for ($i = 0; $i < count($types); $i++) {
        $types[$i] = ContentEntityNormalizer::escapePrefix($types[$i], $context['namespaces']);
      }
    }

    // Create the array of normalized fields, starting with the URI.
    /* @var $entity \Drupal\Core\Entity\ContentEntityInterface */
    $normalized = $normalized + [
      '@graph' => [
        $this->getEntityUri($entity) => [
          '@id' => $this->getEntityUri($entity),
          '@type' => $types,
        ],
      ],
    ];

    // If the fields to use were specified, only output those field values.
    // We could make use of this context key
    // To limit json-ld output to an subset
    // that is just compatible with fcrepo4 and LDP?
    if (isset($context['included_fields'])) {
      $fields = [];
      foreach ($context['included_fields'] as $field_name) {
        $fields[] = $entity->get($field_name);
      }
    }
    else {
      $fields = $entity->getFields();
    }

    $context['current_entity_id'] = $this->getEntityUri($entity);
    $context['current_entity_rdf_mapping'] = $rdf_mappings;

    foreach ($fields as $name => $field) {
      // Just process fields that have rdf mappings defined.
      // We could also pass as not contextualized keys the others
      // if needed.
      if (!empty($rdf_mappings->getPreparedFieldMapping($name))) {
        // Continue if the current user does not have access to view this field.
        if (!$field->access('view', $context['account'])) {
          continue;
        }
        // This tells consecutive calls to content entity normalisers
        // that @context is not needed again.
        $normalized_property = $this->serializer->normalize($field, $format, $context);
        // $this->serializer in questions does implement normalize
        // but the interface (typehint) does not.
        // We could check if serializer implements normalizer interface
        // to avoid any possible errors in case someone swaps serializer.
        $normalized = array_merge_recursive($normalized, $normalized_property);
      }
    }
    // Clean up @graph if this is the top-level entity
    // by converting from associative to numeric indexed.
    if (!$context['embedded']) {
      $normalized['@graph'] = array_values($normalized['@graph']);
    }

    if (isset($context['depth']) && $context['depth'] == 0) {
      $this->moduleHandler->invokeAll(self::NORMALIZE_ALTER_HOOK,
        [$entity, &$normalized, $context]
      );
    }
    return $normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {

    // Get type, necessary for determining which bundle to create.
    if (!isset($context['bundle_type_id'])) {
      throw new UnexpectedValueException('The ' . IslandoraConstants::ISLANDORA_BUNDLE_HEADER . ' header must be specified.');
    }

    $bundle_type_id = $context['bundle_type_id'];
    $entity_type_id = $context['entity_type_id'];

    // Create the entity.
    // $typed_data_ids = $this->getTypedDataIds($target_id, $context);.
    $entity_type = $this->entityManager->getDefinition($entity_type_id);
    $langcode_key = $entity_type->getKey('langcode');
    $values = [];

    // Figure out the language to use.
    if (isset($data[$langcode_key])) {
      $values[$langcode_key] = $data[$langcode_key][0]['value'];
      // Remove the langcode so it does not get iterated over below.
      unset($data[$langcode_key]);
    }

    if ($entity_type->hasKey('bundle')) {
      $bundle_key = $entity_type->getKey('bundle');
      // $values[$bundle_key] = $typed_data_ids['bundle'];.
      $values[$bundle_key] = $bundle_type_id;
      // Unset the bundle key from data, if it's there.
      unset($data[$bundle_key]);
    }

    $entity = $this->entityManager->getStorage($entity_type_id)->create($values);
    $arrFieldsWithRDFMapping = $this->getFieldsWithRdfMapping($entity_type_id, $bundle_type_id);

    // Sort the fields by rdf mapping and get fieldNames for comparison.
    asort($arrFieldsWithRDFMapping);
    $fieldNames = array_keys($arrFieldsWithRDFMapping);

    // Get the expanded JsonLD of the Entity.
    $arrEntityExpandedJsonLD = $this->getEntityExpandedJsonLd($entity_type_id, $bundle_type_id, $arrFieldsWithRDFMapping);
    $arrEntityExpandedJsonLD = json_decode(JsonLD::toString($arrEntityExpandedJsonLD[0], TRUE), TRUE);

    $data = $data["@graph"][0];

    // Iterate through remaining items in data array. These should all
    // correspond to fields.
    foreach ($data as $property => $field_data) {
      // We need to get the field_name via the RDF Mapping.
      $fieldKey = array_search($property, array_keys($arrEntityExpandedJsonLD));
      if ($property == "@type" || $property == "@id") {
        continue;
      }

      $field_name = $fieldNames[$fieldKey];
      $compact_property = $arrFieldsWithRDFMapping[$field_name];
      $field_names = $this->getFields($compact_property, $arrFieldsWithRDFMapping);

      $this->denormalizeMultipleFields($entity, $field_names, $field_data, $format, $context);
    }
    return $entity;
  }

  /**
   * Constructs the entity URI.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The entity URI.
   */
  protected function getEntityUri(EntityInterface $entity) {

    // Some entity types don't provide a canonical link template, at least call
    // out to ->url().
    if ($entity->isNew() || !$entity->hasLinkTemplate('canonical')) {
      return $entity->toUrl('canonical', []);
    }
    $url = $entity->toUrl('canonical', ['absolute' => TRUE]);
    return $url->setRouteParameter('_format', 'jsonld')->toString();
  }

  /**
   * Gets the typed data IDs for a type URI.
   *
   * @param array $types
   *   The type array(s) (value of the 'type' attribute of the incoming data).
   * @param array $context
   *   Context from the normalizer/serializer operation.
   *
   * @return array
   *   The typed data IDs.
   */
  protected function getTypedDataIds(array $types, array $context = []) {

    // The 'type' can potentially contain an array of type objects. By default,
    // Drupal only uses a single type in serializing, but allows for multiple
    // types when deserializing.
    if (isset($types['href'])) {
      $types = [$types];
    }

    foreach ($types as $type) {
      if (!isset($type['href'])) {
        throw new UnexpectedValueException('Type must contain an \'href\' attribute.');
      }
      $type_uri = $type['href'];
      // Check whether the URI corresponds to a known type on this site. Break
      // once one does.
      if ($typed_data_ids = $this->linkManager->getTypeInternalIds($type['href'], $context)) {
        break;
      }
    }

    // If none of the URIs correspond to an entity type on this site, no entity
    // can be created. Throw an exception.
    if (empty($typed_data_ids)) {
      throw new UnexpectedValueException(sprintf('Type %s does not correspond to an entity on this site.', $type_uri));
    }

    return $typed_data_ids;
  }

  /**
   * Returns the RDF Mapping of the fields, if RDF Mapping is available.
   *
   * @param string $entity_type
   *   Entity Type's name.
   * @param string $bundle
   *   Bundle's name.
   *
   * @return array
   *   Field to RDF Mapping.
   */
  private function getFieldsWithRdfMapping($entity_type, $bundle) {
    $arrFieldsWithRDFMapping = [];

    // Get Fields.
    $fields = $this->entityManager->getFieldDefinitions($entity_type, $bundle);

    // Get RDF Mapping.
    $rdfMapping = RdfMapping::load($entity_type . "." . $bundle);
    if (!$rdfMapping) {
      return $arrFieldsWithRDFMapping;
    }

    foreach ($fields as $field_name => $field_definition) {
      $arrFieldMapping = $rdfMapping->getFieldMapping($field_name);
      if (isset($arrFieldMapping['properties']) && count($arrFieldMapping["properties"]) > 0) {
        $arrFieldsWithRDFMapping[$field_name] = $arrFieldMapping["properties"][0];
      }
    }

    return $arrFieldsWithRDFMapping;
  }

  /**
   * Get Expanded JsonLD of the Entity.
   *
   * @param string $entity_type
   *   Entity type's name.
   * @param string $bundle
   *   Bundle's name.
   * @param array $arrFieldsWithRDFMapping
   *   Field name and rdf mapping array.
   *
   * @return array
   *   Expanded JsonLD of the Entity.
   */
  private function getEntityExpandedJsonLd($entity_type, $bundle, array $arrFieldsWithRDFMapping) {

    // Get Context.
    $jsonldGenerator = \Drupal::service('islandora.jsonldcontextgenerator');

    $bundleContext = $jsonldGenerator->getContext($entity_type . "." . $bundle);
    $contextInfo = json_decode($bundleContext);

    // Put fields into a document.
    $arrEntityDocument = [];
    foreach ($arrFieldsWithRDFMapping as $k => $v) {
      $arrEntityDocument[$v] = '';
    }

    $compacted = JsonLD::compact((object) $arrEntityDocument, (object) $contextInfo);
    $entityExpandedJsonLD = JsonLD::expand($compacted);

    return $entityExpandedJsonLD;
  }

  /**
   * Get all fields matching the RDF mapping.
   *
   * @param string $compact_property
   *   Compact RDF mapping.
   * @param array $arrFieldsWithRDFMapping
   *   Field to RDF mapping array.
   *
   * @return array
   *   Field names with same rdf mapping.
   */
  protected function getFields($compact_property, array $arrFieldsWithRDFMapping) {
    $fields = [];
    foreach ($arrFieldsWithRDFMapping as $k => $v) {
      if ($compact_property == $v) {
        array_push($fields, $k);
      }
    }
    return $fields;
  }

  /**
   * Create Fields mapped to same RDF.
   *
   * @param object $entity
   *   Entity.
   * @param array $field_names
   *   Field names with rdf mapping.
   * @param array $field_data
   *   Crosponding data value for the field.
   * @param string $format
   *   Denormalize format.
   * @param string $context
   *   Context.
   */
  protected function denormalizeMultipleFields($entity, array $field_names, array $field_data, $format, $context) {
    foreach ($field_names as &$field_name) {
      $items = $entity->get($field_name);

      // Remove any values that were set as a part of entity creation (e.g
      // uuid). If the incoming field data is set to an empty array, this will
      // also have the effect of emptying the field in REST module.
      $items->setValue([]);
      if ($field_data) {

        // Remove/replace @value.
        foreach ($field_data as $key => $data_arr) {
          if ($data_arr['@value']) {
            $field_data[$key]['value'] = $field_data[$key]['@value'];
            unset($field_data[$key]['@value']);
          }
        }

        // Denormalize the field data into the FieldItemList object.
        $context['target_instance'] = $items;
        $this->serializer->denormalize($field_data, get_class($items), $format, $context);
      }
    }
  }

}
