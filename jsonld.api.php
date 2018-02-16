<?php

/**
 * @file
 * Hooks and stuff.
 */

use Drupal\Core\Entity\EntityInterface;

/**
 * Hook to alter the jsonld normalized array before it is encoded to json.
 *
 * @param \Drupal\Core\Entity\EntityInterface $entity
 *   The entity we are normalizing.
 * @param array $normalized
 *   The current normalized array.
 * @param array $context
 *   The context for the normalization.
 */
function hook_jsonld_alter_normalized_array(EntityInterface $entity, array &$normalized, array $context) {
  if ($entity->getEntityTypeId() == 'node') {
    if (isset($normalized['@graph'])) {
      if (!is_array($normalized["@graph"])) {
        $normalized['@graph'] = [$normalized['@graph']];
      }
      $normalized['@graph'][] = [
        '@id' => 'http://example.org/first/name',
        '@type' => 'schemaOrg:Person',
      ];
    }
  }
}
