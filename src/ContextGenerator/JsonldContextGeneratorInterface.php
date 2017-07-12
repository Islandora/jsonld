<?php

namespace Drupal\jsonld\ContextGenerator;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\rdf\RdfMappingInterface;

/**
 * Interface for a service that provides per Bundle JSON-LD Context generation.
 *
 * @ingroup: jsonld
 */
interface JsonldContextGeneratorInterface {

  /**
   * Generates an JSON-LD Context string based on an RdfMapping object.
   *
   * @param \Drupal\rdf\Entity\RdfMapping|RdfMappingInterface $mapping
   *   An RDF Mapping Object.
   *
   * @return string
   *   A JSON-LD @context as string.
   *
   * @throws \Exception
   *    If no RDF mapping has no rdf:type assigned.
   */
  public function generateContext(RdfMappingInterface $mapping);

  /**
   * Returns an JSON-LD Context string.
   *
   * This method should be invoked if caching and speed is required.
   *
   * @param string $ids
   *   In the form of "entity_type.bundle_name".
   *
   * @return string
   *   A JSON-LD @context as string.
   *
   * @throws \Exception
   *    If no RDF mapping exists.
   */
  public function getContext($ids);

  /**
   * Gets the correct piece of @context for a given entity field.
   *
   * @param \Drupal\rdf\RdfMappingInterface $rdfMapping
   *   Rdf mapping object.
   * @param string $field_name
   *   The name of the field.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The definition of the field.
   * @param array $allRdfNameSpaces
   *   Every RDF prefixed namespace in this Drupal.
   *
   * @return array
   *   Piece of JSON-LD context that supports this field
   */
  public function getFieldsRdf(RdfMappingInterface $rdfMapping, $field_name, FieldDefinitionInterface $fieldDefinition, array $allRdfNameSpaces);

}
