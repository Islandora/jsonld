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
   *     - pass_target_id: (true/false) should we return a target ID
   *       when no term is found?
   *
   * @return mixed
   *   In order of availability:
   *     - the replaced URI string,
   *     - the target entity's label,
   *     - (if pass_target_id is true) the target entity's ID,
   *     - or an empty string.
   */
  public static function linkFieldPassthrough($target, array $arguments) {
    if (is_a($target, 'Drupal\Core\Entity\FieldableEntityInterface')) {
      if (empty($target->get($arguments['link_field'])->uri)) {
        return $target->label();
      }
      else {
        return $target->get($arguments['link_field'])->uri;
      }
    }

    if (is_array($target) && array_key_exists('target_id', $target)) {
      $ent = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($target['target_id']);
      if ($ent && !empty($ent->get($arguments['link_field'])->uri)) {
        return $ent->get($arguments['link_field'])->uri;
      }
      elseif ($ent) {
        return $ent->get('name')->value;
      }
      elseif (array_key_exists('pass_target_id', $arguments) && $arguments['pass_target_id']) {
        return $target['target_id'];
      }
    }
    // Nothing worked, so return nothing.
    return '';
  }

}
