<?php

namespace Drupal\jsonld\Encoder;

use Symfony\Component\Serializer\Encoder\JsonEncoder as SymfonyJsonEncoder;

/**
 * Encodes to JSON-LD.
 *
 * Simply respond to jsonld format requests using the JSON encoder.
 */
class JsonldEncoder extends SymfonyJsonEncoder {

  /**
   * The formats that this Encoder supports.
   *
   * @var string
   * @see src/JsonldServiceProvider.php
   */
  protected $format = 'jsonld';

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format) {

    return $format == $this->format;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding($format) {

    return $format == $this->format;
  }

}
