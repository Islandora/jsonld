<?php

namespace Drupal\Tests\jsonld\Kernel\Normalizer;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\jsonld\Kernel\JsonldKernelTestBase;
use Drupal\user\Entity\User;

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

    $target_user = User::create([
      'name' => $this->randomMachineName(),
      'langcode' => 'en',
    ]);
    $target_user->save();

    rdf_get_mapping('entity_test', 'entity_test')->setBundleMapping(
      [
        'types' => [
          "schema:ImageObject",
        ],
      ])->setFieldMapping('field_test_text', [
        'properties' => ['dc:description'],
      ])->setFieldMapping('user_id', [
        'properties' => ['schema:author'],
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
      'name' => $this->randomMachineName(),
      'type' => 'entity_test',
      'bundle' => 'entity_test',
      'user_id' => $target_user->id(),
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
              "@value" => $values['field_test_text']['value'],
              "@language" => "en",
            ],
          ],
          "http://purl.org/dc/terms/title" => [
            [
              "@value" => $values['name'],
              "@language" => "en",
            ],
          ],
          "http://schema.org/author" => [
            [
              "@id" => $this->getEntityUri($target_user),
            ],
          ],
          "http://schema.org/dateCreated" => [
            [
              "@type" => "http://www.w3.org/2001/XMLSchema#dateTime",
              "@value" => $created_iso,
            ],
          ],
        ],
        [
          "@id" => $this->getEntityUri($target_user),
          "@type" => "http://localhost/rest/type/user/user",
        ],
        [
          "@id" => $this->getEntityUri($target_entity),
          "@type" => [
            "http://schema.org/ImageObject",
          ],
        ],
      ],
    ];

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

    $target_entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'langcode' => 'en',
      'field_test_entity_reference' => NULL,
    ]);
    $target_entity->save();

    $target_user = User::create([
      'name' => $this->randomMachineName(),
      'langcode' => 'en',
    ]);
    $target_user->save();

    rdf_get_mapping('entity_test', 'entity_test')->setBundleMapping(
      [
        'types' => [
          "schema:ImageObject",
        ],
      ])->setFieldMapping('field_test_text', [
        'properties' => ['dc:description'],
      ])->setFieldMapping('user_id', [
        'properties' => ['schema:author'],
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
      'user_id' => $target_user->id(),
      'created' => [
        'value' => $created,
      ],
      'field_test_text' => [
        'value' => 'Dude',
        'format' => 'full_html',
      ],
      'field_test_entity_reference' => [
        'target_id' => $target_entity->id(),
      ],
    ];
    
    $valores = [
      'name' => 'En Castellano de Chile',
      'field_test_text' => [
        'value' => 'Muchacho',
        'format' => 'full_html',
      ],
      'field_test_entity_reference' => [
        'target_id' => $target_entity->id(),
      ],
    ];

    $entity = EntityTest::create($values);
    $entity->save();
    $existing_entity_values = $entity->toArray();
    
    // Note: Drupal also generates a new date create and author 
    // When translating but we can't mark that with @language
    
    $translated_entity_array = array_merge($existing_entity_values , $valores);
    $entity->addTranslation('es', $translated_entity_array)->save();

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
          "http://schema.org/author" => [
            [
              "@id" => $this->getEntityUri($target_user),
            ],
            [
              "@id" => $this->getEntityUri($target_user),
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
          "@id" => $this->getEntityUri($target_user),
          "@type" => "http://localhost/rest/type/user/user",
        ],
        [
          "@id" => $this->getEntityUri($target_entity),
          "@type" => [
            "http://schema.org/ImageObject",
          ],
        ],
      ],
    ];

    $normalized = $this->serializer->normalize($entity, $this->format);

    $this->assertEquals($expected, $normalized, "Did not normalize correctly.");

  }

}
