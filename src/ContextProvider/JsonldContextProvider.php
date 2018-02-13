<?php

namespace Drupal\jsonld\ContextProvider;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class JsonldContextProvider.
 *
 * @package Drupal\jsonld\ContextProvider
 */
class JsonldContextProvider implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * The current entity.
   *
   * @var array
   */
  protected $entity;

  /**
   * JsonldContextProvider constructor.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for this context.
   */
  public function __construct(EntityInterface $entity) {
    $this->entity = $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $context_definition = new ContextDefinition('any', $this->t('runtime context normalized array from jsonld'), FALSE);
    $context = new Context($context_definition, $this->entity);
    return ['@jsonld.jsonld_normalized_context_provider:array' => $context];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $context = new Context(new ContextDefinition('any', $this->t('normalized array from jsonld')));
    return ['@jsonld.jsonld_normalized_context_provider:array' => $context];
  }

}
