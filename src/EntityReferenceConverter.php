<?php

namespace Drupal\jsonld;

/**
 * Converts EntityReferenceField targets.
 */
class EntityReferenceConverter {

  /**
   * Swaps out an Entity's URI with the value in a field.
   *
   * @param array|\Drupal\Core\Entity\EntityInterface $target
   *   Either the target of the entity reference field being converted
   *   (as the JSON-LD module does) or an array with 'target_id'
   *   (as the RDF module does).
   * @param array $arguments
   *   An array of arguments defined in the mapping.
   *   Expected keys are:
   *     - link_field: The field used to store the URI we will use.
   *
   * @return mixed
   *   Either the replaced URI string OR the targeted entity if no URI.
   */
  public static function linkFieldPassthrough($target, array $arguments) {
    if (is_a($target, 'Drupal\Core\Entity\FieldableEntityInterface') && !empty($target->get($arguments['link_field'])->uri)) {
      return $target->get($arguments['link_field'])->uri;
    }

    if (is_array($target) && array_key_exists('target_id', $target)) {
      $ent = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($target['target_id']);
      if (!empty($ent->get($arguments['link_field'])->uri)) {
        return $ent->get($arguments['link_field'])->uri;
      }
      else {
        return $ent->get('name')->value;
      }
    }
    // We don't have a value to pass, so don't bother converting.
    return $target;
  }

}
