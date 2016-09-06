<?php
/**
 * Created by PhpStorm.
 * User: dpino
 * Date: 9/1/16
 * Time: 9:33 PM
 */

namespace Drupal\jsonld\Normalizer;

  /**
   * JSON-LD context helper trait.
   *
   * @author Diego Pino Navarro
   *
   * @internal
   */
trait JsonLdContextTrait
{
  /**
   * Adds @context key to a normalized JSON-LD structure.
   *
   * @param ContextBuilderInterface $contextBuilder
   * @param string                  $
   * @param array                   $context
   * @param array                   $data
   *
   * @return array
   */
  private function addJsonLdContext(array &$context, array $data = [])
  {
    if (isset($context['has_context'])) {
      return $data;
    }
    $context['has_context'] = true;
    if (isset($context['embed_context'])) {
      $data['@context'] = "";
      return $data;
    }
    $data['@context'] = "";
    return $data;
  }
}

}