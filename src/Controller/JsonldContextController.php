<?php

namespace Drupal\jsonld\Controller;

use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\jsonld\ContextGenerator\JsonldContextGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\rdf\Entity\RdfMapping;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Class JsonldContextController.
 *
 * @package Drupal\jsonld\Controller
 */
class JsonldContextController extends ControllerBase {

  /**
   * Injected JsonldContextGenerator.
   *
   * @var \Drupal\jsonld\ContextGenerator\JsonldContextGeneratorInterface
   */
  private $jsonldContextGenerator;

  /**
   * JsonldContextController constructor.
   *
   * @param \Drupal\jsonld\ContextGenerator\JsonldContextGeneratorInterface $jsonld_context_generator
   *   Injected JsonldContextGenerator.
   */
  public function __construct(JsonldContextGeneratorInterface $jsonld_context_generator) {
    $this->jsonldContextGenerator = $jsonld_context_generator;
  }

  /**
   * Controller's create method for dependecy injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The App Container.
   *
   * @return static
   *   An instance of our jsonld.contextgenerator service.
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('jsonld.contextgenerator'));
  }

  /**
   * Returns an JSON-LD Context for a entity bundle.
   *
   * @param string $entity_type
   *   Route argument, an entity type.
   * @param string $bundle
   *   Route argument, a bundle.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Symfony Http Request.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   An Http response.
   */
  public function content($entity_type, $bundle, Request $request) {

    // TODO: expose cached/not cached through
    // more varied HTTP response codes.
    try {
      $context = $this->jsonldContextGenerator->getContext("$entity_type.$bundle");
      $response = new CacheableJsonResponse(json_decode($context), 200);
      $response->setEncodingOptions(JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
      $response->headers->set('Content-Type', 'application/ld+json');

      // For now deal with Cache dependencies manually.
      $meta = new CacheableMetadata();
      $meta->setCacheContexts(['user.permissions', 'ip', 'url']);
      $meta->setCacheTags(RdfMapping::load("$entity_type.$bundle")->getCacheTags());
      $meta->setCacheMaxAge(Cache::PERMANENT);
      $response->addCacheableDependency($meta);
    }
    catch (\Exception $e) {
      $response = new Response($e->getMessage(), 400);
    }

    return $response;
  }

}
