<?php

namespace Drupal\jsonld\Utils;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for dependency injection of JSON-LD normalizer utilities.
 * @package Drupal\jsonld\Utils
 */
interface JsonldNormalizerUtilsInterface {

  /**
   * Constructs the entity URI.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The entity URI.
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   *   When $entity->toUrl() fails.
   */
  public function getEntityUri(EntityInterface $entity);

}
