<?php

namespace Drupal\Tests\jsonld\Kernel;

use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Entity\EntityInterface;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\jsonld\Encoder\JsonldEncoder;
use Drupal\jsonld\Normalizer\ContentEntityNormalizer;
use Drupal\jsonld\Normalizer\EntityReferenceItemNormalizer;
use Drupal\jsonld\Normalizer\FieldItemNormalizer;
use Drupal\jsonld\Normalizer\FieldNormalizer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\hal\LinkManager\LinkManager;
use Drupal\hal\LinkManager\RelationLinkManager;
use Drupal\hal\LinkManager\TypeLinkManager;
use Drupal\serialization\EntityResolver\ChainEntityResolver;
use Drupal\serialization\EntityResolver\TargetIdResolver;
use Drupal\serialization\EntityResolver\UuidResolver;
use Drupal\user\Entity\User;
use Symfony\Component\Serializer\Serializer;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\TypedData\TranslationStatusInterface;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Base class for Json-LD Kernel tests.
 */
abstract class JsonldKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'hal',
    'serialization',
    'rdf',
    'rdf_test_namespaces',
    'entity_test',
    'text',
    'jsonld',
    'language',
    'content_translation',
  ];

  /**
   * The mock serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected $serializer;

  /**
   * The format being tested.
   *
   * @var string
   */
  protected $format = 'jsonld';

  /**
   * The class name of the test class.
   *
   * @var string
   */
  protected $entityClass = 'Drupal\entity_test\Entity\EntityTest';

  /**
   * The RDF mapping for our tests.
   *
   * @var \Drupal\rdf\Entity\RdfMapping
   */
  protected $rdfMapping;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_test');
    
    // Create the default languages.
    $this->installConfig(['language']);
    $this->installEntitySchema('configurable_language');

    // Create test languages.
    ConfigurableLanguage::createFromLangcode('es')->save();

    $class = get_class($this);
    while ($class) {
      if (property_exists($class, 'modules')) {
        // Only check the modules, if the $modules property was not inherited.
        $rp = new \ReflectionProperty($class, 'modules');
        if ($rp->class == $class) {
          foreach (array_intersect(['node', 'comment'], $class::$modules) as $module) {
            $this->installEntitySchema($module);
          }
        }
      }
      $class = get_parent_class($class);
    }

    $this->installSchema('system', ['sequences']);

    $types = ['schema:Thing'];
    $created_mapping = [
      'properties' => ['schema:dateCreated'],
      'datatype' => 'xsd:dateTime',
      'datatype_callback' => ['callable' => 'Drupal\rdf\CommonDataConverter::dateIso8601Value'],
    ];

    // Save bundle mapping config.
    $this->rdfMapping = rdf_get_mapping('entity_test', 'entity_test')
      ->setBundleMapping(['types' => $types])
      ->setFieldMapping('created', $created_mapping)
      ->setFieldMapping('name', [
        'properties' => ['dc:title'],
        'datatype' => 'xsd:string',
      ])
      ->setFieldMapping('field_test_text', [
        'properties' => ['dc:abstract'],
        'datatype' => 'xsd:string',
      ])->setFieldMapping('field_test_entity_reference', [
        'properties' => ['dc:references'],
        'datatype' => 'xsd:nonNegativeInteger',
      ])
      ->save();

    // Create the test text field.
    FieldStorageConfig::create([
      'field_name' => 'field_test_text',
      'entity_type' => 'entity_test',
      'type' => 'text',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test_text',
      'bundle' => 'entity_test',
      'translatable' => TRUE,
    ])->save();

    // Create the test entity reference field.
    FieldStorageConfig::create([
      'field_name' => 'field_test_entity_reference',
      'entity_type' => 'entity_test',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'entity_test',
      ],
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_test_entity_reference',
      'bundle' => 'entity_test',
      'translatable' => FALSE,
    ])->save();

    $entity_manager = \Drupal::entityManager();
    $link_manager = new LinkManager(new TypeLinkManager(new MemoryBackend('default'), \Drupal::moduleHandler(), \Drupal::service('config.factory'), \Drupal::service('request_stack'), \Drupal::service('entity_type.bundle.info')), new RelationLinkManager(new MemoryBackend('default'), $entity_manager, \Drupal::moduleHandler(), \Drupal::service('config.factory'), \Drupal::service('request_stack')));

    $chain_resolver = new ChainEntityResolver([new UuidResolver($entity_manager), new TargetIdResolver()]);

    $jsonld_context_generator = $this->container->get('jsonld.contextgenerator');

    // Set up the mock serializer.
    $normalizers = [
      new ContentEntityNormalizer($link_manager, $entity_manager, \Drupal::moduleHandler()),
      new EntityReferenceItemNormalizer($link_manager, $chain_resolver, $jsonld_context_generator),
      new FieldItemNormalizer($jsonld_context_generator),
      new FieldNormalizer(),
    ];

    $encoders = [
      new JsonldEncoder(),
    ];
    $this->serializer = new Serializer($normalizers, $encoders);
  }

  /**
   * Constructs the entity URI.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The entity URI.
   */
  protected function getEntityUri(EntityInterface $entity) {

    // Some entity types don't provide a canonical link template, at least call
    // out to ->url().
    if ($entity->isNew() || !$entity->hasLinkTemplate('canonical')) {
      return $entity->toUrl('canonical', []);
    }
    $url = $entity->toUrl('canonical', ['absolute' => TRUE]);
    return $url->setRouteParameter('_format', 'jsonld')->toString();
  }

  /**
   * Generate a test entity and the expected normalized array.
   *
   * @return array
   *   with [ the entity, the normalized array ].
   */
  protected function generateTestEntity() {
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
              "@type" => "http://www.w3.org/2001/XMLSchema#string",
              "@value" => $values['field_test_text']['value'],
            ],
          ],
          "http://purl.org/dc/terms/title" => [
            [
              "@type" => "http://www.w3.org/2001/XMLSchema#string",
              "@value" => $values['name'],
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

    return [$entity, $expected];
  }

}
