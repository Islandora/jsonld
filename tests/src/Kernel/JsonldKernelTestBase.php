<?php

namespace Drupal\Tests\jsonld\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\jsonld\Encoder\JsonldEncoder;
use Drupal\jsonld\Normalizer\ContentEntityNormalizer;
use Drupal\jsonld\Normalizer\EntityReferenceItemNormalizer;
use Drupal\jsonld\Normalizer\FieldItemNormalizer;
use Drupal\jsonld\Normalizer\FieldNormalizer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\serialization\EntityResolver\ChainEntityResolver;
use Drupal\serialization\EntityResolver\TargetIdResolver;
use Drupal\user\Entity\User;
use Symfony\Component\Serializer\Serializer;
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
    'rest',
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
   * The Language manager for our tests.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * A route provider for our tests.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->languageManager = \Drupal::service('language_manager');
    $this->routeProvider = \Drupal::service('router.route_provider');

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
      'translatable' => FALSE,
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

    $entity_manager = \Drupal::service('entity_type.manager');
    $link_manager = \Drupal::service('rest.link_manager');
    $uuid_resolver = \Drupal::service('serializer.entity_resolver.uuid');
    $chain_resolver = new ChainEntityResolver([$uuid_resolver, new TargetIdResolver()]);

    $jsonld_context_generator = $this->container->get('jsonld.contextgenerator');

    // Set up the mock serializer.
    $normalizers = [
      new ContentEntityNormalizer($link_manager, $entity_manager, \Drupal::moduleHandler(), \Drupal::service('config.factory'), $this->languageManager, $this->routeProvider),
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
   * Generate a test entity and the expected normalized array.
   *
   * @return array
   *   with [ the entity, the normalized array ].
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Problem saving the entity.
   * @throws \Exception
   *   Problem creating a DateTime.
   */
  protected function generateTestEntity() {
    $target_entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'langcode' => 'en',
      'field_test_entity_reference' => NULL,
    ]);
    $target_entity->getFieldDefinition('created')->setTranslatable(FALSE);
    $target_entity->getFieldDefinition('user_id')->setTranslatable(FALSE);
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

    $id = "http://localhost/entity_test/" . $entity->id() . "?_format=jsonld";
    $target_id = "http://localhost/entity_test/" . $target_entity->id() . "?_format=jsonld";
    $user_id = "http://localhost/user/" . $target_user->id() . "?_format=jsonld";

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

    return [$entity, $expected];
  }

}
