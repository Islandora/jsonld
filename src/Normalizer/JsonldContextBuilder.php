<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 8/21/16
 * Time: 1:15 PM
 */

namespace Drupal\jsonld\Normalizer;


use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;

interface JsonldContextBuilder {
 
  /**
   * Gets the base context based on global RDF namespaces.
   *
   * @param int $referenceType
   *
   * @return array
   */
  public function getBaseContext();

  /**
   * Builds the JSON-LD context based on route entry point.
   *
   * @param $Url
   *
   * @return array
   */
  public function getEntrypointContext($Url UrlGeneratorInterface);
  /**
   * Builds the JSON-LD context for the given Drupal Entity.
   *
   * @param string $resourceClass
   * @param int    $referenceType
   *
   * @throws ResourceClassNotFoundException
   *
   * @return array
   */
  public function getResourceContext($Entity ContentEntityInterface)
  /**
   * Gets the URI of the given resource context.
   *
   * @param string $resourceClass
   * @param int    $referenceType
   *
   * @return string
   */
  public function getResourceContextUri($Entity ContentEntityInterface);
}

}