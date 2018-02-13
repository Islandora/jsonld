<?php

namespace Drupal\jsonld\ContextReaction;

use Drupal\context\ContextReactionPluginBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Base class to alter the normalizes Json-ld.
 *
 * Plugins must extend this class to be considered for execution.
 *
 * @package Drupal\jsonld\ContextReaction
 */
abstract class NormalizerAlterReaction extends ContextReactionPluginBase {

  /**
   * This reaction takes can alter the array of json-ld built from the entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The entity we are normalizing.
   * @param array|null $normalized
   *   The normalized json-ld before encoding.
   * @param array|null $context
   *   The context used in the normalizer.
   */
  abstract public function execute(EntityInterface $entity = NULL, array &$normalized = NULL, array $context = NULL);

}
