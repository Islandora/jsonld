<?php

namespace Drupal\jsonld\Normalizer;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\hal\LinkManager\LinkManagerInterface;
use GuzzleHttp\ClientInterface;

/**
 * Converts the Drupal entity object structure to a JSON-LD array structure.
 */
class FileEntityNormalizer extends ContentEntityNormalizer {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\file\FileInterface';

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The Drupal file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a FileEntityNormalizer object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP Client.
   * @param \Drupal\hal\LinkManager\LinkManagerInterface $link_manager
   *   The hypermedia link manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager,
                              ClientInterface $http_client,
                              LinkManagerInterface $link_manager,
                              ModuleHandlerInterface $module_handler,
                              FileSystemInterface $file_system) {

    parent::__construct($link_manager, $entity_manager, $module_handler);

    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {

    $data = parent::normalize($entity, $format, $context);
    // Replace the file url with a full url for the file.
    $data['uri'][0]['value'] = $this->getEntityUri($entity);

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {

    $file_data = (string) $this->httpClient->get($data['uri'][0]['value'])->getBody();

    $path = 'temporary://' . $this->fileSystem->basename($data['uri'][0]['value']);
    $data['uri'] = file_unmanaged_save_data($file_data, $path);

    return $this->entityManager->getStorage('file')->create($data);
  }

}
