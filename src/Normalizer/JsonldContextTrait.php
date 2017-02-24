<?php

namespace Drupal\jsonld\Normalizer;

/**
   * JSON-LD context helper trait.
   *
   * @author Diego Pino Navarro
   *
   * @internal
   */
trait JsonLdContextTrait {

  /**
   * Adds @context key to a normalized JSON-LD structure.
   *
   * @param array $context
   *   Context (iteration) this runs.
   * @param array $data
   *   A JSON-LD @context data structure.
   *
   * @return array
   *   A JSON-LD @context data structure.
   */
  private function addJsonLdContext(array &$context, array $data = []) {
    if (isset($context['has_context'])) {
      return $data;
    }
    $context['has_context'] = TRUE;
    if (isset($context['embed_context'])) {
      $data['@context'] = "";
      return $data;
    }
    $data['@context'] = "";
    return $data;
  }

}
