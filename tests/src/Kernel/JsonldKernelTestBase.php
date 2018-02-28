<?php

namespace Drupal\Tests\jsonld\Kernel;

use Drupal\Core\Cache\MemoryBackend;
use Drupal\Core\Entity\EntityInterface;
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
      return $entity->url('canonical', []);
    }
    $url = $entity->urlInfo('canonical', ['absolute' => TRUE]);
    return $url->setRouteParameter('_format', 'jsonld')->toString();
  }

}
