<?php

namespace Drupal\jsonld\Normalizer;

use Drupal\serialization\Normalizer\NormalizerBase as SerializationNormalizerBase;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Base class for Normalizers.
 */
abstract class NormalizerBase extends SerializationNormalizerBase implements DenormalizerInterface {

  /**
   * The formats that the Normalizer can handle.
   *
   * @var array
   */
  protected $formats = ['jsonld'];

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {

    return in_array($format, $this->formats) && parent::supportsNormalization($data, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {

    if (in_array($format, $this->formats) && (class_exists($this->supportedInterfaceOrClass) || interface_exists($this->supportedInterfaceOrClass))) {
      $target = new \ReflectionClass($type);
      $supported = new \ReflectionClass($this->supportedInterfaceOrClass);
      if ($supported->isInterface()) {
        return $target->implementsInterface($this->supportedInterfaceOrClass);
      }
      else {
        return ($target->getName() == $this->supportedInterfaceOrClass || $target->isSubclassOf($this->supportedInterfaceOrClass));
      }
    }

    return FALSE;
  }

  /**
   * Escapes namespace prefixes in predicates.
   *
   * @param string $predicate
   *   The predicate whose namespace you wish to escape.
   * @param array $namespaces
   *   Associative array of namespaces keyed by prefix.
   *
   * @return string
   *   The predicate with escaped namespace prefix.
   */
  public static function escapePrefix($predicate, array $namespaces) {

    $exploded = explode(":", $predicate, 2);
    if (!isset($namespaces[$exploded[0]])) {
      return $predicate;
    }
    return $namespaces[$exploded[0]] . $exploded[1];
  }

  /**
   * Deduplicate lists of @types.
   *
   * @param array $array
   *   The array to deduplicate.
   *
   * @return array
   *   The deduplicated array.
   */
  protected static function deduplicateTypes(array $array): array {
    if (isset($array['@graph'])) {
      // Should only be run on a top level Jsonld array.
      foreach ($array['@graph'] as $key => $value) {
        if (isset($array['@graph'][$key]['@type']) && is_array($array['@graph'][$key]['@type'])) {
          // Deduplicate @types
          $array['@graph'][$key]['@type'] = array_unique($array['@graph'][$key]['@type']);
        }
      }
    }
    return $array;
  }

}
