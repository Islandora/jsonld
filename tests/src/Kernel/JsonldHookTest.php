<?php

namespace Drupal\Tests\jsonld\Kernel;

/**
 * Tests the module hooks.
 *
 * @package Drupal\Tests\jsonld\Kernel
 * @group jsonld
 */
class JsonldHookTest extends JsonldKernelTestBase {

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
    'json_alter_normalize_hooks',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() : void {
    parent::setUp();
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Test hook alter.
   */
  public function testAlterNormalizedJsonld() {

    list($entity, $expected) = $this->generateTestEntity();
    $expected['@graph'][] = [
      "@id" => "json_alter_normalize_hooks",
      "http://purl.org/dc/elements/1.1/title" => "The hook is tested.",
    ];

    $normalized = $this->serializer->normalize($entity, $this->format);
    $this->assertEquals($expected, $normalized, "Did not normalize and call hooks correctly.");

  }

}
