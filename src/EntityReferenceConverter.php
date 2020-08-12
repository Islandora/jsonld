<?php

namespace Drupal\jsonld;

use Drupal\Core\Entity\EntityInterface;

/**
 * Converts EntityReferenceField targets.
 */
class EntityReferenceConverter {

  /**
   * Swaps out an Entity's URI with the value in a field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $target
   *   The target of the entity reference field being converted.
   * @param array $arguments
   *   An array of arguments defined in the mapping.
   *   Expected keys are:
   *     - link_field: The field used to store the URI we will use.
   *
   * @return mixed
   *   Either the replaced URI string OR the targeted entity if no URI.
   */
  public static function linkFieldPassthrough(EntityInterface $target, array $arguments) {
    if (!empty($target->get($arguments['link_field'])->uri)) {
      return $target->get($arguments['link_field'])->uri;
    }
    // We don't have a value to pass, so don't bother converting.
    return $target;
  }

}
