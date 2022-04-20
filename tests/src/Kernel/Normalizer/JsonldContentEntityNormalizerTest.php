<?php

namespace Drupal\Tests\jsonld\Kernel\Normalizer;

use Drupal\Tests\jsonld\Kernel\JsonldKernelTestBase;
use Drupal\Tests\jsonld\Kernel\JsonldTestEntityGenerator;

/**
 * Tests the JSON-LD Normalizer.
 *
 * @group jsonld
 */
class JsonldContentEntityNormalizerTest extends JsonldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * @covers \Drupal\jsonld\Normalizer\NormalizerBase::supportsNormalization
   * @covers \Drupal\jsonld\Normalizer\NormalizerBase::escapePrefix
   * @covers \Drupal\jsonld\Normalizer\ContentEntityNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\FieldNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\FieldNormalizer::normalizeFieldItems
   * @covers \Drupal\jsonld\Normalizer\FieldItemNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\EntityReferenceItemNormalizer::normalize
   * @covers \Drupal\jsonld\Utils\JsonldNormalizerUtils::getEntityUri
   */
  public function testSimpleNormalizeJsonld() {

    list($entity, $expected) = JsonldTestEntityGenerator::create()->generateNewEntity();

    $normalized = $this->serializer->normalize($entity, $this->format);
    $this->assertEquals($expected, $normalized, "Did not normalize correctly.");

  }

  /**
   * @covers \Drupal\jsonld\Normalizer\NormalizerBase::supportsNormalization
   * @covers \Drupal\jsonld\Normalizer\NormalizerBase::escapePrefix
   * @covers \Drupal\jsonld\Normalizer\ContentEntityNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\FieldNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\FieldNormalizer::normalizeFieldItems
   * @covers \Drupal\jsonld\Normalizer\FieldItemNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\EntityReferenceItemNormalizer::normalize
   * @covers \Drupal\jsonld\Utils\JsonldNormalizerUtils::getEntityUri
   */
  public function testLocalizedNormalizeJsonld() {

    list($entity, $expected) = JsonldTestEntityGenerator::create()->generateNewEntity();

    $existing_entity_values = $entity->toArray();
    $target_entity_tl_id = $existing_entity_values['field_test_entity_reference'][0]['target_id'];

    $valores = [
      'name' => 'En Castellano de Chile',
      'field_test_text' => [
        'value' => 'Muchacho',
        'format' => 'full_html',
      ],
      'field_test_entity_reference' => [
        'target_id' => $target_entity_tl_id,
      ],
    ];

    // Note: Drupal also generates a new date create and author
    // When translating but we can't mark that with @language
    $translated_entity_array = array_merge($existing_entity_values, $valores);
    $entity->addTranslation('es', $translated_entity_array)->save();

    $expected['@graph'][0]["http://purl.org/dc/terms/description"][] =
      [
        "@value" => "Muchacho",
        "@language" => "es",
      ];

    $expected['@graph'][0]["http://purl.org/dc/terms/title"][] =
      [
        "@value" => "En Castellano de Chile",
        "@language" => "es",
      ];

    $normalized = $this->serializer->normalize($entity, $this->format);

    $this->assertEquals($expected, $normalized, "Did not normalize correctly.");

  }

  /**
   * Where multiple referenced entities are tied to the same rdf mapping.
   */
  public function testDeduplicateEntityReferenceMappings(): void {

    list($entity, $expected) = JsonldTestEntityGenerator::create()->makeDuplicateReferenceMapping()->generateNewEntity();

    $normalized = $this->serializer->normalize($entity, $this->format);

    $this->assertEquals($expected, $normalized, "Did not normalize correctly.");
  }

  /**
   * Test where multiple fields rdf mapping is referencing the same entity.
   */
  public function testDeduplicateEntityReferenceIds(): void {

    list($entity, $expected) = JsonldTestEntityGenerator::create()->makeDuplicateReference()->generateNewEntity();

    $normalized = $this->serializer->normalize($entity, $this->format);

    $this->assertEquals($expected, $normalized, "Did not normalize correctly.");
  }

  /**
   * Test where multiple fields are.
   *
   *  - are referencing the same entity.
   *  - sharing the same RDF mapping.
   */
  public function testDuplicateEntityReferenceAndMappings(): void {
    list($entity, $expected) = JsonldTestEntityGenerator::create()->makeDuplicateReference()->makeDuplicateReferenceMapping()
      ->generateNewEntity();

    $normalized = $this->serializer->normalize($entity, $this->format);

    $this->assertEquals($expected, $normalized, "Did not normalize correctly.");
  }

}
