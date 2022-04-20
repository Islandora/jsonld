<?php

namespace Drupal\jsonld\Normalizer;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\hal\LinkManager\LinkManagerInterface;
use Drupal\jsonld\Utils\JsonldNormalizerUtilsInterface;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

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
   * Json-ld normalizer utilities.
   *
   * @var \Drupal\Jsonld\Utils\JsonldNormalizerUtilsInterface
   */
  protected $utils;

  /**
   * Constructs an ContentEntityNormalizer object.
   *
   * @param \Drupal\hal\LinkManager\LinkManagerInterface $link_manager
   *   The hypermedia link manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Jsonld\Utils\JsonldNormalizerUtilsInterface $normalizer_utils
   *   The json-ld normalizer utilities.
   */
  public function __construct(LinkManagerInterface $link_manager,
                              EntityTypeManagerInterface $entity_manager,
                              ModuleHandlerInterface $module_handler,
                              JsonldNormalizerUtilsInterface $normalizer_utils) {

    $this->linkManager = $link_manager;
    $this->entityManager = $entity_manager;
    $this->moduleHandler = $module_handler;
    $this->utils = $normalizer_utils;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {

    // We need to make sure that this only runs for JSON-LD.
    // @todo check $format before going RDF crazy
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
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $normalized = $normalized + [
      '@graph' => [
        $this->utils->getEntityUri($entity) => [
          '@id' => $this->utils->getEntityUri($entity),
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

    $context['current_entity_id'] = $this->utils->getEntityUri($entity);
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
        $normalized = NestedArray::mergeDeepArray(
          [
            $normalized,
            $normalized_property,
          ]
        );
        // Deduplicate the @type elements and arrays of entity references.
        $normalized = self::deduplicateTypesAndReferences($normalized);
      }
    }
    // Clean up @graph if this is the top-level entity
    // by converting from associative to numeric indexed.
    if (!$context['embedded']) {
      $normalized['@graph'] = array_values($normalized['@graph']);
    }

    if (isset($context['depth']) && $context['depth'] == 0) {
      if (!isset($context['utils'])) {
        // Pass the normalizer utils to the invoking methods.
        $context['utils'] = $this->utils;
      }
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
    if (!isset($data['_links']['type'])) {
      throw new UnexpectedValueException('The type link relation must be specified.');
    }

    // Create the entity.
    $typed_data_ids = $this->getTypedDataIds($data['_links']['type'], $context);
    $entity_type = $this->entityManager->getDefinition($typed_data_ids['entity_type']);
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
      $values[$bundle_key] = $typed_data_ids['bundle'];
      // Unset the bundle key from data, if it's there.
      unset($data[$bundle_key]);
    }

    $entity = $this->entityManager->getStorage($typed_data_ids['entity_type'])->create($values);

    // Remove links from data array.
    unset($data['_links']);
    // Get embedded resources and remove from data array.
    $embedded = [];
    if (isset($data['_embedded'])) {
      $embedded = $data['_embedded'];
      unset($data['_embedded']);
    }

    // Flatten the embedded values.
    foreach ($embedded as $relation => $field) {
      $field_ids = $this->linkManager->getRelationInternalIds($relation);
      if (!empty($field_ids)) {
        $field_name = $field_ids['field_name'];
        $data[$field_name] = $field;
      }
    }

    // Pass the names of the fields whose values can be merged.
    $entity->_restSubmittedFields = array_keys($data);

    // Iterate through remaining items in data array. These should all
    // correspond to fields.
    foreach ($data as $field_name => $field_data) {
      $items = $entity->get($field_name);
      // Remove any values that were set as a part of entity creation (e.g
      // uuid). If the incoming field data is set to an empty array, this will
      // also have the effect of emptying the field in REST module.
      $items->setValue([]);
      if ($field_data) {
        // Denormalize the field data into the FieldItemList object.
        $context['target_instance'] = $items;
        $this->serializer->denormalize($field_data, get_class($items), $format, $context);
      }
    }

    return $entity;
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

}
