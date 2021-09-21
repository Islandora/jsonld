<?php

namespace Drupal\jsonld\Utils;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drupal\jsonld\Form\JsonLdSettingsForm;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Utilities used both in JSON-LD and by modules utilizing the alter hook.
 *
 * @package Drupal\jsonld\Utils
 */
class JsonldNormalizerUtils implements JsonldNormalizerUtilsInterface {

  /**
   * The configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private $languageManager;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  private $routeProvider;

  /**
   * NormalizerUtils constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    LanguageManagerInterface $language_manager,
    RouteProviderInterface $route_provider
  ) {
    $this->config = $config_factory->get(JsonLdSettingsForm::CONFIG_NAME);
    $this->languageManager = $language_manager;
    $this->routeProvider = $route_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityUri(EntityInterface $entity) {

    // Some entity types don't provide a canonical link template, at least call
    // out to ->url().
    if ($entity->isNew() || !$entity->hasLinkTemplate('canonical')) {
      if ($entity->getEntityTypeId() == 'file') {
        return $entity->createFileUrl(FALSE);
      }
      return "";
    }

    try {
      $undefined = $this->languageManager->getLanguage('und');
      $entity_type = $entity->getEntityTypeId();

      // This throws the RouteNotFoundException if the route doesn't exist.
      $this->routeProvider->getRouteByName("rest.entity.$entity_type.GET");

      $url = Url::fromRoute(
        "rest.entity.$entity_type.GET",
        [$entity_type => $entity->id()],
        ['absolute' => TRUE, 'language' => $undefined]
      );
    }
    catch (RouteNotFoundException $e) {
      $url = $entity->toUrl('canonical', ['absolute' => TRUE]);
    }
    if (!$this->config->get(JsonLdSettingsForm::REMOVE_JSONLD_FORMAT)) {
      $url->setRouteParameter('_format', 'jsonld');
    }
    return $url->toString();
  }

}
