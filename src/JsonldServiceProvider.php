<?php

namespace Drupal\Jsonld;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Adds JSON-LD as known format.
 */
class JsonldServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {

    if ($container->has('http_middleware.negotiation') && is_a(
        $container->getDefinition('http_middleware.negotiation')
          ->getClass(), '\Drupal\Core\StackMiddleware\NegotiationMiddleware', TRUE
    )
    ) {
      $container->getDefinition('http_middleware.negotiation')
        ->addMethodCall('registerFormat', ['jsonld', ['application/ld+json']]);
    }
  }

}
