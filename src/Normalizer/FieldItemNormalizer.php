<?php

namespace Drupal\jsonld\Normalizer;

use Drupal\Core\Field\FieldItemInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * Converts the Drupal field item object structure to JSON-LD array structure.
 */
class FieldItemNormalizer extends NormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\Core\Field\FieldItemInterface';

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = array()) {

    // @TODO Understand Drupal complex fields to RDF mapping
    // Fields can be complex, with multiple subfields
    // but i'm not sure if rdf module
    // is able to assign that, so investigate:
    $values = $field_item->toArray();
    // For now we will just pass @value and @language to json-ld
    // Until we find a way of mapping to rdf subfields.
    $values_clean = array();
    $normalized = array();
    $field = $field_item->getParent();
    if (!isset($values['value'])) {
      // Makes little sense to add to json-ld without a value.
      return [];
    }
    else {
      $values_clean['@value'] = $values['value'];
      if (isset($context['current_entity_rdf_mapping'])) {
        // So why i am passing the whole rdf mapping object and not
        // only the predicate? Well because i hope i will be able
        // to MAP to RDF also sub fields of a complex field someday
        // and somehow.
        $field_mappings = $context['current_entity_rdf_mapping']->getPreparedFieldMapping($field->getName());
        $field_keys = isset($field_mappings['properties']) ? $field_mappings['properties'] : array($field->getName());
        if (!empty($field_mappings['datatype'])) {
          $values_clean['@type'] = $field_mappings['datatype'];
        }

        // Well, this is json-ld but $field_item->toArray() depends on
        // each field implementation and some don't have 'value key'
        // Maybe i should handle them as _blank nodes?
        // For now this is a dirty solution.
        if (!empty($field_mappings['datatype_callback'])) {
          $callback = $field_mappings['datatype_callback']['callable'];
          $arguments = isset($field_mappings['datatype_callback']['arguments']) ? $field_mappings['datatype_callback']['arguments'] : NULL;
          $values_clean['@value'] = call_user_func($callback, $values, $arguments);
        }
      }
      else {
        $field_keys = array($field->getName());
      }

      if (isset($context['langcode'])) {
        $values_clean['@language'] = $context['langcode'];
      }
      array_filter($values_clean);
      // The values are wrapped in an array, and then wrapped in another array
      // keyed by field name so that field items can be merged by the
      // FieldNormalizer.
      foreach ($field_keys as $field_name) {
        // If there's no context, we need full predicates, not shortened ones.
        if (!$context['needs_jsonldcontext']) {
          $field_name = $this->escapePrefix($field_name, $context['namespaces']);
        }
        $normalized[$field_name] = array($values_clean);
      }

      return $normalized;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = array()) {

    if (!isset($context['target_instance'])) {
      throw new InvalidArgumentException('$context[\'target_instance\'] must be set to denormalize with the FieldItemNormalizer');
    }
    if ($context['target_instance']->getParent() == NULL) {
      throw new InvalidArgumentException('The field item passed in via $context[\'target_instance\'] must have a parent set.');
    }

    $field_item = $context['target_instance'];

    // If this field is translatable, we need to create a translated instance.
    if (isset($data['lang'])) {
      $langcode = $data['lang'];
      unset($data['lang']);
      $field_definition = $field_item->getFieldDefinition();
      if ($field_definition->isTranslatable()) {
        $field_item = $this->createTranslatedInstance($field_item, $langcode);
      }
    }

    $field_item->setValue($this->constructValue($data, $context));
    return $field_item;
  }

  /**
   * Build the field item value using the incoming data.
   *
   * @param array $data
   *   The incoming data for this field item.
   * @param array $context
   *   The context passed into the Normalizer.
   *
   * @return mixed
   *   The value to use in Entity::setValue().
   */
  protected function constructValue(array $data, array $context) {

    return $data;
  }

  /**
   * Get a translated version of the field item instance.
   *
   * To indicate that a field item applies to one translation of an entity and
   * not another, the property path must originate with a translation of the
   * entity. This is the reason for using target_instances, from which the
   * property path can be traversed up to the root.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The untranslated field item instance.
   * @param string $langcode
   *   The langcode.
   *
   * @return \Drupal\Core\Field\FieldItemInterface
   *   The translated field item instance.
   */
  protected function createTranslatedInstance(FieldItemInterface $item, $langcode) {

    // Remove the untranslated item that was created for the default language
    // by FieldNormalizer::denormalize().
    $items = $item->getParent();
    $delta = $item->getName();
    unset($items[$delta]);

    // Instead, create a new item for the entity in the requested language.
    $entity = $item->getEntity();
    $entity_translation = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity->addTranslation($langcode);
    $field_name = $item->getFieldDefinition()->getName();
    return $entity_translation->get($field_name)->appendItem();
  }

}
