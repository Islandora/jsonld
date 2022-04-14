<?php

namespace Drupal\Tests\jsonld\Kernel;

use Drupal\Component\Utility\Random;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\jsonld\ContextGenerator\JsonldContextGenerator;

/**
 * Tests the Json-LD context Generator methods and simple integration.
 *
 * @group jsonld
 * @coversDefaultClass \Drupal\jsonld\ContextGenerator\JsonldContextGenerator
 */
class JsonldContextGeneratorTest extends JsonldKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'entity_test',
    'hal',
    'jsonld',
    'rdf',
    'rdf_test_namespaces',
    'serialization',
    'system',
    'user',
  ];

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityBundleListenerInterface
   */
  protected $entityBundleListener;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The ContextGenerator we are testing.
   *
   * @var \Drupal\jsonld\ContextGenerator\JsonldContextGeneratorInterface
   */
  protected $theJsonldContextGenerator;

  /**
   * {@inheritdoc}
   */
  public function setUp() :void {
    parent::setUp();

    $types = ['schema:Thing'];
    $mapping = [
      'properties' => ['schema:dateCreated'],
      'datatype' => 'xsd:dateTime',
      'datatype_callback' => ['callable' => 'Drupal\rdf\CommonDataConverter::dateIso8601Value'],
    ];

    // Save bundle mapping config.
    rdf_get_mapping('entity_test', 'rdf_source')
      ->setBundleMapping(['types' => $types])
      ->setFieldMapping('created', $mapping)
      ->save();
    // Initialize our generator.
    $this->theJsonldContextGenerator = new JsonldContextGenerator(
        $this->container->get('entity_field.manager'),
        $this->container->get('entity_type.bundle.info'),
        $this->container->get('entity_type.manager'),
        $this->container->get('cache.default'),
        $this->container->get('logger.channel.jsonld')
      );

  }

  /**
   * @covers \Drupal\jsonld\ContextGenerator\JsonldContextGenerator::getContext
   */
  public function testGetContext() {
    // Test with known asserts.
    $context = $this->theJsonldContextGenerator->getContext('entity_test.rdf_source');
    $context_as_array = json_decode($context, TRUE);
    $this->assertTrue(is_array($context_as_array), 'JSON-LD Context generated has correct structure for known Bundle');

    $this->assertTrue(strpos($context, '"schema": "http://schema.org/"') !== FALSE, "JSON-LD Context generated contains the expected values for known Bundle");

  }

  /**
   * Tests Exception in case of no rdf type.
   *
   * @covers \Drupal\jsonld\ContextGenerator\JsonldContextGenerator::getContext
   */
  public function testGetContextException() {
    $this->expectException(\Exception::class);
    // This should throw the expected Exception.
    $newEntity = $this->createContentType();
    $this->theJsonldContextGenerator->getContext('entity_test.' . $newEntity->id());
  }

  /**
   * @covers \Drupal\jsonld\ContextGenerator\JsonldContextGenerator::generateContext
   */
  public function testGenerateContext() {
    // Test with known asserts.
    $rdfMapping = rdf_get_mapping('entity_test', 'rdf_source');
    $context = $this->theJsonldContextGenerator->generateContext($rdfMapping);
    $context_as_array = json_decode($context, TRUE);
    $this->assertTrue(is_array($context_as_array), 'JSON-LD Context generated has correct structure for known Bundle');

    $this->assertTrue(strpos($context, '"schema": "http://schema.org/"') !== FALSE, "JSON-LD Context generated contains the expected values for known Bundle");

  }

  /**
   * Tests Exception in case of no rdf type.
   *
   * @covers \Drupal\jsonld\ContextGenerator\JsonldContextGenerator::generateContext
   */
  public function testGenerateContextException() {
    $this->expectException(\Exception::class);
    // This should throw the expected Exception.
    $newEntity = $this->createContentType();
    $rdfMapping = rdf_get_mapping('entity_test', $newEntity->id());
    $this->theJsonldContextGenerator->getContext('entity_test.' . $newEntity->id());
  }

  /**
   * Helper function to generate a fake bundle.
   *
   * @param array $values
   *   Array of values to create the bundle.
   *
   * @return \Drupal\entity_test\Entity\EntityTest
   *   A new bundle.
   */
  private function createContentType(array $values = []) {
    // Find a non-existent random type name.
    $random = new Random();
    if (!isset($values['type'])) {
      do {
        $id = strtolower($random->string(8));
      } while (EntityTest::load($id));
    }
    else {
      $id = $values['type'];
    }
    $values += [
      'id' => $id,
      'label' => $id,
    ];
    $type = EntityTest::create($values);
    $type->save();
    return $type;
  }

}
