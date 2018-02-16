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

}
