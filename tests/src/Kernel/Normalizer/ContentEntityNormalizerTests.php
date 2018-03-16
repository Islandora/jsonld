<?php

namespace Drupal\Tests\jsonld\Kernel\Normalizer;

use Drupal\Tests\jsonld\Kernel\JsonldKernelTestBase;

/**
 * Class ContentEntityTests.
 *
 * @group jsonld
 */
class ContentEntityNormalizerTests extends JsonldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * @covers \Drupal\jsonld\Normalizer\NormalizerBase::supportsNormalization
   * @covers \Drupal\jsonld\Normalizer\NormalizerBase::escapePrefix
   * @covers \Drupal\jsonld\Normalizer\ContentEntityNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\ContentEntityNormalizer::getEntityUri
   * @covers \Drupal\jsonld\Normalizer\FieldNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\FieldNormalizer::normalizeFieldItems
   * @covers \Drupal\jsonld\Normalizer\FieldItemNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\EntityReferenceItemNormalizer::normalize
   */
  public function testSimpleNormalizeJsonld() {

    list($entity, $expected) = $this->generateTestEntity();

    $normalized = $this->serializer->normalize($entity, $this->format);
    $this->assertEquals($expected, $normalized, "Did not normalize correctly.");

  }

  /**
   * @covers \Drupal\jsonld\Normalizer\NormalizerBase::supportsNormalization
   * @covers \Drupal\jsonld\Normalizer\NormalizerBase::escapePrefix
   * @covers \Drupal\jsonld\Normalizer\ContentEntityNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\ContentEntityNormalizer::getEntityUri
   * @covers \Drupal\jsonld\Normalizer\FieldNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\FieldNormalizer::normalizeFieldItems
   * @covers \Drupal\jsonld\Normalizer\FieldItemNormalizer::normalize
   * @covers \Drupal\jsonld\Normalizer\EntityReferenceItemNormalizer::normalize
   */
  public function testLocalizedNormalizeJsonld() {

    list($entity, $expected) = $this->generateTestEntity();

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

}
