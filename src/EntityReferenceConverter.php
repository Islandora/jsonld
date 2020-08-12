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
   *   Either the target of the entity reference field being converted (JSON-LD module)
   *   or an array with 'target_id' (RDF module).
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
    // We don't have a value to pass, so don't bother converting.
    return $target;
  }

}
