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
  public function supportsNormalization($data, string $format = NULL, array $context = []): bool {

    return in_array($format, $this->formats) && parent::supportsNormalization($data, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, string $type, string $format = NULL, array $context = []): bool {

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
   * Deduplicate lists of @types and predicate to entity references.
   *
   * @param array $array
   *   The array to deduplicate.
   *
   * @return array
   *   The deduplicated array.
   */
  protected static function deduplicateTypesAndReferences(array $array): array {
    if (isset($array['@graph'])) {
      // Should only be run on a top level Jsonld array.
      foreach ($array['@graph'] as $object_key => $object_value) {
        foreach ($object_value as $key => $values) {
          if ($key == '@type' && is_array($values)) {
            $array['@graph'][$object_key]['@type'] = array_unique($values);
          }
          elseif ($key != '@id' && is_array($array['@graph'][$object_key][$key])
            && count($array['@graph'][$object_key][$key]) > 1) {
            $array['@graph'][$object_key][$key] = self::deduplicateArrayOfIds($array['@graph'][$object_key][$key]);
          }
        }
      }
    }
    return $array;
  }

  /**
   * Deduplicate multi-dimensional array based on the `@id` value.
   *
   * @param array $array
   *   The multi-dimensional array.
   *
   * @return array
   *   The deduplicated multi-dimensional array.
   */
  private static function deduplicateArrayOfIds(array $array): array {
    $temp_array = [];
    foreach ($array as $val) {
      if (!array_key_exists('@id', $val) || array_search($val['@id'], array_column($temp_array, '@id')) === FALSE) {
        $temp_array[] = $val;
      }
    }
    return $temp_array;
  }

}
