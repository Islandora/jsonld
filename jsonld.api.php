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
 *   The context for the normalization
 *
 * $context['utils'] contains an instance of \Drupal\jsonld\Utils\JsonldNormalizerUtils, this provides
 * the getEntityUri() method to correctly generate a URI with/without the ?format=jsonld suffix.
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

/**
 * Hook to alter the field type mappings.
 *
 * Be aware that drupal field definitions can be complex.
 * e.g text_with_summary has a text, a summary, a number of lines, etc
 * we are only dealing with the resulting ->value() of all this separate
 * pieces and mapping only that as a whole.
 *
 * @return string[]
 *   An associative array of field type mappings where the key is the field type
 *   and the value is the type mapping.
 */
function hook_jsonld_field_mappings() {
  return [
    "string" => [
      "@type" => "xsd:string",
    ],
  ];
}
