<?php

namespace Drupal\jsonld\Normalizer;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\hal\LinkManager\LinkManagerInterface;
use Drupal\jsonld\ContextGenerator\JsonldContextGeneratorInterface;
use Drupal\serialization\EntityResolver\EntityResolverInterface;
use Drupal\serialization\EntityResolver\UuidReferenceInterface;

/**
 * Converts the Drupal entity reference item object to JSON-LD array structure.
 */
class EntityReferenceItemNormalizer extends FieldItemNormalizer implements UuidReferenceInterface {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem';

  /**
   * The hypermedia link manager.
   *
   * @var \Drupal\hal\LinkManager\LinkManagerInterface
   */
  protected $linkManager;

  /**
   * The entity resolver.
   *
   * @var \Drupal\serialization\EntityResolver\EntityResolverInterface
   */
  protected $entityResolver;

  /**
   * Constructs an EntityReferenceItemNormalizer object.
   *
   * @param \Drupal\hal\LinkManager\LinkManagerInterface $link_manager
   *   The hypermedia link manager.
   * @param \Drupal\serialization\EntityResolver\EntityResolverInterface $entity_Resolver
   *   The entity resolver.
   * @param \Drupal\jsonld\ContextGenerator\JsonldContextGeneratorInterface $jsonld_context
   *   The Json-Ld context service.
   */
  public function __construct(LinkManagerInterface $link_manager,
  EntityResolverInterface $entity_Resolver,
                              JsonldContextGeneratorInterface $jsonld_context) {
    parent::__construct($jsonld_context);
    $this->linkManager = $link_manager;
    $this->entityResolver = $entity_Resolver;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {

    /* @var $field_item \Drupal\Core\Field\FieldItemInterface */
    $target_entity = $field_item->get('entity')->getValue();
    $normalized_prop = [];
    // If this is not a content entity, let the parent implementation handle it,
    // only content entities are supported as embedded resources.
    if (!($target_entity instanceof FieldableEntityInterface)) {
      return parent::normalize($field_item, $format, $context);
    }
    // If the parent entity passed in a langcode, unset it before normalizing
    // the target entity. Otherwise, untranslatable fields of the target entity
    // will include the langcode.
    $langcode = isset($context['langcode']) ? $context['langcode'] : NULL;
    unset($context['langcode']);
    // Limiting to uuid makes sure that we only get one child from base entity
    // if not we could end traversing forever since there is no way
    // we can enforce acyclic entity references.
    $context['included_fields'] = ['uuid'];
    $context['needs_jsonldcontext'] = FALSE;
    $context['embedded'] = TRUE;

    // The normalized object we will collect entries into.
    $normalized_in_context = [];

    if (isset($context['current_entity_rdf_mapping'])) {
      $values_clean = [];
      // So why i am passing the whole rdf mapping object and not
      // only the predicate? Well because i hope i will be able
      // to MAP to RDF also sub fields of a complex field someday
      // and somehow.
      $field_mappings = $context['current_entity_rdf_mapping']->getPreparedFieldMapping(
        $field_item->getParent()
          ->getName()
      );
      $field_keys = isset($field_mappings['properties']) ?
            $field_mappings['properties'] :
            [$field_item->getParent()->getName()];

      // Value in this case is the target entity, so if a callback exists
      // it should work against that?
      if (!empty($field_mappings['datatype_callback'])) {
        $callback = $field_mappings['datatype_callback']['callable'];
        $arguments = isset($field_mappings['datatype_callback']['arguments']) ? $field_mappings['datatype_callback']['arguments'] : NULL;
        $transformed_value = call_user_func($callback, $target_entity, $arguments);
        if (!empty($field_mappings['datatype']) && $field_mappings['datatype'] == '@id') {
          $values_clean['@id'] = $transformed_value;
          $values_clean['@type'] = '@id';
        }
        else {
          $values_clean['@value'] = $transformed_value;
        }
      }
      else {
        // Normalize the target entity.
        // This will call \Drupal\jsonld\Normalizer\ContentEntityNormalizer.
        $normalized_in_context = $this->serializer->normalize($target_entity, $format, $context);
        // Since getting the to embed entity URL here could be a little bit
        // expensive and would require an helper method
        // i could just borrow it from above.
        $values_clean['@id'] = key($normalized_in_context['@graph']);
      }

      // The returned structure will be recursively merged into the normalized
      // JSON-LD @Graph.
      foreach ($field_keys as $field_name) {
        // If there's no context, we need full predicates, not shortened ones.
        if (!$context['needs_jsonldcontext']) {
          $field_name = $this->escapePrefix($field_name, $context['namespaces']);
          foreach ($values_clean as $key => $val) {
            // Expand values in the array, ie. @type values xsd:string.
            $values_clean[$key] = $this->escapePrefix($val, $context['namespaces']);
          }
        }
        $normalized_prop[$field_name] = [$values_clean];
      }

    }

    $normalized_in_context = array_merge_recursive($normalized_in_context, ['@graph' => [$context['current_entity_id'] => $normalized_prop]]);

    return $normalized_in_context;
  }

  /**
   * {@inheritdoc}
   */
  protected function constructValue(array $data, array $context) {

    $field_item = $context['target_instance'];
    $field_definition = $field_item->getFieldDefinition();
    $target_type = $field_definition->getSetting('target_type');
    $id = $this->entityResolver->resolve($this, $data, $target_type);
    if (isset($id)) {
      return ['target_id' => $id];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid($data) {

    if (isset($data['uuid'])) {
      $uuid = $data['uuid'];
      // The value may be a nested array like $uuid[0]['value'].
      if (is_array($uuid) && isset($uuid[0]['value'])) {
        $uuid = $uuid[0]['value'];
      }
      return $uuid;
    }
  }

}
