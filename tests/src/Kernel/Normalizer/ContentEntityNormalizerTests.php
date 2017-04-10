<?php

namespace Drupal\Tests\jsonld\Kernel\Normalizer;

use Drupal\entity_test\Entity\EntityTest;
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
    $target_entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'langcode' => 'en',
      'field_test_entity_reference' => NULL,
    ]);
    $target_entity->save();

    $tz = new \DateTimeZone('UTC');
    $dt = new \DateTime(NULL, $tz);
    $created = $dt->format("U");
    $iso = $dt->format(\DateTime::W3C);
    // Create an entity.
    $values = [
      'langcode' => 'en',
      'name' => $this->randomMachineName(),
      'type' => 'entity_test',
      'bundle' => 'entity_test',
      'created' => [
        'value' => $created,
      ],
      'field_test_text' => [
        'value' => $this->randomMachineName(),
        'format' => 'full_html',
      ],
      'field_test_entity_reference' => [
        'target_id' => $target_entity->id(),
      ],
    ];

    $entity = EntityTest::create($values);
    $entity->save();

    $expected = [
      '@graph' => [
        [
          '@id' => $this->getEntityUri($entity),
          '@type' => ['http://schema.org/Thing'],
          'http://purl.org/dc/terms/title' => [
            [
              '@value' => $values['name'],
              '@type' => 'xsd:string',
              '@language' => 'en',
            ],
          ],
          'http://schema.org/dateCreated' => [
            [
              '@value' => $iso,
              '@type' => 'xsd:dateTime',
              '@language' => 'en',
            ],
          ],
          'http://purl.org/dc/terms/abstract' => [
            [
              '@value' => $values['field_test_text']['value'],
              '@type' => 'xsd:string',
            ],
          ],
          'http://purl.org/dc/terms/references' => [
            [
              '@id' => $this->getEntityUri($target_entity),
              '@type' => 'xsd:nonNegativeInteger',
            ],
          ],
        ],
        [
          '@id' => $this->getEntityUri($target_entity),
          '@type' => ['http://schema.org/Thing'],
        ],
      ],
    ];

    $normalized = $this->serializer->normalize($entity, $this->format);
    $this->assertEquals($expected, $normalized, "Did not normalize correctly.");

  }

}
