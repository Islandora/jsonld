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

    $target_entity_tl = EntityTest::create([
      'name' => $this->randomMachineName(),
      'langcode' => 'ho',
      'field_test_entity_reference' => NULL,
    ]);
    $target_entity_tl->save();

    $target_user_tl = User::create([
      'name' => 'Tooh',
      'langcode' => 'ho',
    ]);
    $target_user_tl->save();

    rdf_get_mapping('entity_test', 'entity_test')->setBundleMapping(
      [
        'types' => [
          "schema:ImageObject",
        ],
      ])->setFieldMapping('field_test_text', [
        'properties' => ['dc:description'],
      ])->setFieldMapping('user_id', [
        'properties' => ['schema:contributor'],
      ])->setFieldMapping('modified', [
        'properties' => ['schema:dateModified'],
        'datatype' => 'xsd:dateTime',
      ])->save();

    $tz = new \DateTimeZone('UTC');
    $dt = new \DateTime(NULL, $tz);
    $created = $dt->format("U");
    $created_iso = $dt->format(\DateTime::W3C);
    // Create an entity.
    $values = [
      'langcode' => 'en',
      'name' => 'In Canadian english',
      'type' => 'entity_test',
      'bundle' => 'entity_test',
      'user_id' => $target_user_tl->id(),
      'created' => [
        'value' => $created,
      ],
      'field_test_text' => [
        'value' => 'Dude',
        'format' => 'full_html',
      ],
      'field_test_entity_reference' => [
        'target_id' => $target_entity_tl->id(),
      ],
    ];

    $valores = [
      'name' => 'En Castellano de Chile',
      'field_test_text' => [
        'value' => 'Muchacho',
        'format' => 'full_html',
      ],
      'field_test_entity_reference' => [
        'target_id' => $target_entity_tl->id(),
      ],
    ];

    $entity_tl = EntityTest::create($values);
    $entity_tl->save();
    $existing_entity_values = $entity_tl->toArray();

    // Note: Drupal also generates a new date create and author
    // When translating but we can't mark that with @language
    $translated_entity_array = array_merge($existing_entity_values, $valores);
    $entity_tl->addTranslation('es', $translated_entity_array)->save();

    $expected = [
      "@graph" => [
        [
          "@id" => $this->getEntityUri($entity),
          "@type" => [
            'http://schema.org/ImageObject',
          ],
          "http://purl.org/dc/terms/references" => [
            [
              "@id" => $this->getEntityUri($target_entity),
            ],
          ],
          "http://purl.org/dc/terms/description" => [
            [
              "@value" => "Dude",
              "@language" => "en",
            ],
            [
              "@value" => "Muchacho",
              "@language" => "es",
            ],
          ],
          "http://purl.org/dc/terms/title" => [
            [
              "@value" => "In Canadian english",
              "@language" => "en",
            ],
            [
              "@value" => "En Castellano de Chile",
              "@language" => "es",
            ],
          ],
          "http://schema.org/contributor" => [
            [
              "@id" => $this->getEntityUri($target_user_tl),
            ],
            [
              "@id" => $this->getEntityUri($target_user_tl),
            ],
          ],
          "http://schema.org/dateCreated" => [
            [
              "@type" => "http://www.w3.org/2001/XMLSchema#dateTime",
              "@value" => $created_iso,
            ],
            [
              "@type" => "http://www.w3.org/2001/XMLSchema#dateTime",
              "@value" => $created_iso,
            ],
          ],
        ],
        [
          "@id" => $this->getEntityUri($target_user_tl),
          "@type" => "http://localhost/rest/type/user/user",
        ],
        [
          "@id" => $this->getEntityUri($target_entity_tl),
          "@type" => [
            "http://schema.org/ImageObject",
          ],
        ],
      ],
    ];

    $normalized = $this->serializer->normalize($entity_tl, $this->format);

    $this->assertEquals($expected, $normalized, "Did not normalize correctly.");

  }

}
