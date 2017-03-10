<?php

namespace Drupal\jsonld\EventSubscriber;

use Drupal\Core\EventSubscriber\ExceptionJsonSubscriber;

/**
 * Handle JSON-LD exceptions the same as JSON exceptions.
 */
class ExceptionJsonldSubscriber extends ExceptionJsonSubscriber {

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {

    return ['jsonld'];
  }

}
