<?php

namespace Drupal\Tests\jsonld\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\Tests\RandomGeneratorTrait;
use Drupal\user\Entity\User;

/**
 * Class to allow modification of RDF mapping before generating the entity.
 */
class JsonldTestEntityGenerator {

  use RandomGeneratorTrait;

  /**
   * The user used in the the test object.
   *
   * @var \Drupal\user\Entity\User
   */
  private $user;

  /**
   * The first test entity referenced in the test object.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  private $referrableEntity1;

  /**
   * The second test entity referenced in the test object.
   *
   * @var \Drupal\entity_test\Entity\EntityTest
   */
  private $referrableEntity2;

  /**
   * Constants for swapping around predicates.
   */
  private const DCTERMS_URL = "http://purl.org/dc/terms/";

  private const DCTERMS_PUBLISHER = self::DCTERMS_URL . "publisher";

  private const DCTERMS_REFERENCES = self::DCTERMS_URL . "references";

  /**
   * The predicate used for the first referenced entity.
   *
   * @var string
   */
  private $referableEntity1Predicate = self::DCTERMS_REFERENCES;

  /**
   * The predicate used for the second referenced entity.
   *
   * @var string
   */
  private $referrableEntity2Predicate = self::DCTERMS_PUBLISHER;

  /**
   * Basic constructor.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function __construct() {
    $this->referrableEntity1 = $this->generateReferrableEntity();
    $this->referrableEntity1->save();

    $this->referrableEntity2 = $this->generateReferrableEntity();
    $this->referrableEntity2->save();

    $this->user = User::create([
      'name' => $this->randomMachineName(),
      'langcode' => 'en',
    ]);
    $this->user->save();

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
  }

  /**
   * Static construction method.
   *
   * @return \Drupal\Tests\jsonld\Kernel\JsonldTestEntityGenerator
   *   A new test entity generator.
   */
  public static function create() {
    return new JsonldTestEntityGenerator();
  }

  /**
   * Create a new random entity.
   *
   * @return \Drupal\entity_test\Entity\EntityTest
   *   The new test entity.
   */
  private function generateReferrableEntity(): EntityTest {
    $target_entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'langcode' => 'en',
      'field_test_entity_reference' => NULL,
    ]);
    $target_entity->getFieldDefinition('created')->setTranslatable(FALSE);
    $target_entity->getFieldDefinition('user_id')->setTranslatable(FALSE);
    return $target_entity;
  }

  /**
   * Make both reference fields point to the same entity.
   *
   * @return \Drupal\Tests\jsonld\Kernel\JsonldTestEntityGenerator
   *   This test entity generator.
   */
  public function makeDuplicateReference(): JsonldTestEntityGenerator {
    $this->referrableEntity2 = $this->referrableEntity1;
    return $this;
  }

  /**
   * Make reference field 2 use the same RDF predicate as reference field 1.
   *
   * @return \Drupal\Tests\jsonld\Kernel\JsonldTestEntityGenerator
   *   This test entity generator.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Error altering the RDF mapping.
   */
  public function makeDuplicateReferenceMapping(): JsonldTestEntityGenerator {
    rdf_get_mapping('entity_test', 'entity_test')
      ->setFieldMapping('field_test_entity_reference2', [
        'properties' => ['dc:references'],
        'datatype' => 'xsd:nonNegativeInteger',
      ]
    )->save();
    $this->referrableEntity2Predicate = $this->referableEntity1Predicate;
    return $this;
  }

  /**
   * Generate the entity and expected Json-ld array.
   *
   * @return array
   *   Array of [ entity , expected jsonld array ].
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Error saving the entity.
   */
  public function generateNewEntity(): array {
    $dt = new \DateTime('now', new \DateTimeZone('UTC'));
    $created = $dt->format("U");
    $created_iso = $dt->format(\DateTime::W3C);
    // Create an entity.
    $values = [
      'langcode' => 'en',
      'name' => $this->randomMachineName(),
      'type' => 'entity_test',
      'bundle' => 'entity_test',
      'user_id' => $this->user->id(),
      'created' => [
        'value' => $created,
      ],
      'field_test_text' => [
        'value' => $this->randomMachineName(),
        'format' => 'full_html',
      ],
      'field_test_entity_reference' => [
        'target_id' => $this->referrableEntity1->id(),
      ],
      'field_test_entity_reference2' => [
        'target_id' => $this->referrableEntity2->id(),
      ],
    ];

    $entity = EntityTest::create($values);
    $entity->save();

    $id = "http://localhost/entity_test/" . $entity->id() . "?_format=jsonld";
    $target_id = "http://localhost/entity_test/" . $this->referrableEntity1->id() . "?_format=jsonld";
    $target_id_2 = "http://localhost/entity_test/" . $this->referrableEntity2->id() . "?_format=jsonld";
    $user_id = "http://localhost/user/" . $this->user->id() . "?_format=jsonld";

    $expected = [
      "@graph" => [
        [
          "@id" => $id,
          "@type" => [
            'http://schema.org/ImageObject',
          ],
          "http://purl.org/dc/terms/references" => [
            [
              "@id" => $target_id,
            ],
          ],
          "http://purl.org/dc/terms/publisher" => [
            [
              "@id" => $target_id_2,
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
              "@language" => "en",
              "@value" => $values['name'],
            ],
          ],
          "http://schema.org/author" => [
            [
              "@id" => $user_id,
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
          "@id" => $user_id,
          "@type" => "http://localhost/rest/type/user/user",
        ],
        [
          "@id" => $target_id,
          "@type" => [
            "http://schema.org/ImageObject",
          ],
        ],
      ],
    ];
    // If we mapped two entities to the same predicate we remove one.
    if ($this->referableEntity1Predicate == $this->referrableEntity2Predicate) {
      if ($this->referrableEntity1 !== $this->referrableEntity2) {
        // If there are two different entities referred to, then merge them.
        $expected['@graph'][0][self::DCTERMS_REFERENCES] = array_merge(
          $expected['@graph'][0][self::DCTERMS_REFERENCES],
          $expected['@graph'][0][self::DCTERMS_PUBLISHER]
        );
      }
      unset($expected['@graph'][0][self::DCTERMS_PUBLISHER]);
    }
    // If the 2 referenced entities are different both need to have an entry.
    if ($target_id !== $target_id_2) {
      $expected['@graph'][] = [
        "@id" => $target_id_2,
        "@type" => [
          "http://schema.org/ImageObject",
        ],
      ];
    }

    return [$entity, $expected];
  }

}
