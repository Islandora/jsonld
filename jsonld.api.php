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
function hook_jsonld_alter_field_mappings() {
  return [
    "comment" => [
      "@type" => "xsd:string",
    ],
    "datetime" => [
      "@type" => "xsd:dateTime",
    ],
    "file" => [
      "@type" => "@id",
    ],
    "image" => [
      "@type" => "@id",
    ],
    "link" => [
      "@type" => "xsd:anyURI",
    ],
    "list_float" => [
      "@type" => "xsd:float",
      "@container" => "@list",
    ],
    "list_integer" => [
      "@type" => "xsd:int",
      "@container" => "@list",
    ],
    "list_string" => [
      "@type" => "xsd:string",
      "@container" => "@list",
    ],
    "path" => [
      "@type" => "xsd:anyURI",
    ],
    "text" => [
      "@type" => "xsd:string",
    ],
    "text_with_summary" => [
      "@type" => "xsd:string",
    ],
    "text_long" => [
      "@type" => "xsd:string",
    ],
    "uuid" => [
      "@type" => "xsd:string",
    ],
    "uri" => [
      "@type" => "xsd:anyURI",
    ],
    "language" => [
      "@type" => "xsd:language",
    ],
    "string_long" => [
      "@type" => "xsd:string",
    ],
    "changed" => [
      "@type" => "xsd:dateTime",
    ],
    "map" => "xsd:",
    "boolean" => [
      "@type" => "xsd:boolean",
    ],
    "email" => [
      "@type" => "xsd:string",
    ],
    "integer" => [
      "@type" => "xsd:int",
    ],
    "decimal" => [
      "@type" => "xsd:decimal",
    ],
    "created" => [
      "@type" => "xsd:dateTime",
    ],
    "float" => [
      "@type" => "xsd:float",
    ],
    "entity_reference" => [
      "@type" => "@id",
    ],
    "timestamp" => [
      "@type" => "xsd:dateTime",
    ],
    "string" => [
      "@type" => "xsd:string",
    ],
    "password" => [
      "@type" => "xsd:string",
    ],
  ];
}
