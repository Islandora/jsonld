<?php

namespace Drupal\jsonld\Normalizer;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\hal\LinkManager\LinkManagerInterface;
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
   * @param \Drupal\hal\EntityResolver\EntityResolverInterface $entity_Resolver
   *   The entity resolver.
   */
  public function __construct(LinkManagerInterface $link_manager, EntityResolverInterface $entity_Resolver) {

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
    // Normalize the target entity.
    // This will call \Drupal\jsonld\Normalizer\ContentEntityNormalizer.
    $embedded = $this->serializer->normalize($target_entity, $format, $context);

    if (isset($context['current_entity_rdf_mapping'])) {
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
      if (!empty($field_mappings['datatype'])) {
        $values_clean['@type'] = $field_mappings['datatype'];
      }

      // Value in this case is the target entity, so if a callback exists
      // it should work against that?
      if (!empty($field_mappings['datatype_callback'])) {
        $callback = $field_mappings['datatype_callback']['callable'];
        $arguments = isset($field_mappings['datatype_callback']['arguments']) ? $field_mappings['datatype_callback']['arguments'] : NULL;
        $values_clean['@value'] = call_user_func($callback, $target_entity, $arguments);
      }
      // Since getting the to embed entity URL here could be a little bit
      // expensive and would require an helper method
      // i could just borrow it from the $embed result.
      $values_clean['@id'] = key($embedded['@graph']);

      // The returned structure will be recursively merged into the normalized
      // JSON-LD @Graph.
      foreach ($field_keys as $field_name) {
        // If there's no context, we need full predicates, not shortened ones.
        if (!$context['needs_jsonldcontext']) {
          $field_name = $this->escapePrefix($field_name, $context['namespaces']);
        }
        $normalized_prop[$field_name] = [$values_clean];
      }

    }

    $normalized_in_context = array_merge_recursive($embedded, ['@graph' => [$context['current_entity_id'] => $normalized_prop]]);

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
