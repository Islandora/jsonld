<?php

namespace Drupal\Tests\jsonld\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;

/**
 * Implements WEB tests for Context routing response in various scenarios.
 *
 * @group jsonld
 */
class JsonldContextGeneratorTest extends BrowserTestBase {

  /**
   * A user entity.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  private $user;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'jsonld',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Initial setup tasks that for every method method.
   */
  public function setUp() {
    parent::setUp();

    // Create a test content type.
    $test_type = $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'type' => 'test_type',
      'label' => 'Test Type',
    ]);
    $test_type->save();

    $types = ['schema:Thing'];
    $created_mapping = [
      'properties' => ['schema:dateCreated'],
      'datatype' => 'xsd:dateTime',
      'datatype_callback' => ['callable' => 'Drupal\rdf\CommonDataConverter::dateIso8601Value'],
    ];

    // Give it a basic rdf mapping.
    rdf_get_mapping('node', 'test_type')
      ->setBundleMapping(['types' => $types])
      ->setFieldMapping('created', $created_mapping)
      ->setFieldMapping('title', [
        'properties' => ['dc:title'],
        'datatype' => 'xsd:string',
      ])
      ->save();

    $this->user = $this->drupalCreateUser([
      'administer site configuration',
      'administer nodes',
    ]);

    // Login.
    $this->drupalLogin($this->user);
  }

  /**
   * Tests that the Context Response Page can be reached.
   */
  public function testJsonldcontextPageExists() {
    $url = Url::fromRoute(
      'jsonld.context',
      ['entity_type' => 'node', 'bundle' => 'test_type']
    );
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests that the response is in fact application/ld+json.
   */
  public function testJsonldcontextContentypeheaderResponseIsValid() {
    $url = Url::fromRoute(
      'jsonld.context',
      ['entity_type' => 'node', 'bundle' => 'test_type']
    );
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertEquals($this->drupalGetHeader('Content-Type'), 'application/ld+json', 'Correct JSON-LD mime type was returned');
  }

  /**
   * Tests that the Context received has the basic structural needs.
   */
  public function testJsonldcontextResponseIsValid() {
    $expected = [
      '@context' => [
        'schema' => 'http://schema.org/',
        'dc' => 'http://purl.org/dc/terms/',
        'schema:dateCreated' => [
          '@type' => "xsd:dateTime",
        ],
        'dc:title' => [
          '@type' => 'xsd:string',
        ],
      ],
    ];

    $url = Url::fromRoute(
      'jsonld.context',
      ['entity_type' => 'node', 'bundle' => 'test_type']
    );
    $this->drupalGet($url);
    $this->assertSession()->statusCodeEquals(200);
    $jsonldarray = json_decode($this->getSession()->getPage()->getContent(), TRUE);
    $this->verbose($jsonldarray);
    $this->assertEquals($expected, $jsonldarray, "Returned @context matches expected response.");
  }

}
