<?php

namespace Drupal\vyva;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Utility\Token;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;

/**
 * Virtual Y Video Automation media manager.
 */
class VyvaMediaManager {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The token.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * The file system helper.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a new VyvaMediaManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client.
   * @param \Drupal\Core\Utility\Token $token
   *   The token.
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    ClientInterface $http_client,
    Token $token,
    FileSystemInterface $file_system
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->token = $token;
    $this->fileSystem = $file_system;
  }

  /**
   * Prepare the thumbnail image media entity based on the incoming data.
   *
   * @param array $details
   *   The incoming data.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   A media entity or NULL.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function prepareThumbnailMedia(array $details) {
    if (!$details['thumbnailUrl']) {
      return NULL;
    }

    // Fetch the thumbnail from source.
    try {
      $response = $this->httpClient->request('GET', $details['thumbnailUrl'], [
        'headers' => [
          'accept' => 'image/jpeg;q=0.9,image/png;q=0.1',
        ],
      ]);
    }
    catch (TransferException $e) {
      return NULL;
    }

    $destination = $this->getImageFieldDestination();

    // Decide on the file extension.
    $content_type_header = $response->getHeader('Content-Type');
    $content_type = is_array($content_type_header) ? $content_type_header[0] : $content_type_header;
    $extension = 'jpg';
    if ($content_type == 'image/png') {
      $extension = 'png';
    }

    // Store file locally.
    if (!$this->fileSystem->prepareDirectory($destination, FileSystemInterface::CREATE_DIRECTORY)) {
      return NULL;
    }
    $filename = $this->buildFilename($details);
    $file = file_save_data($response->getBody()->getContents(), "{$destination}/{$filename}.{$extension}");

    // Create media entity.
    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => 'image',
      'status' => 1,
      'uid' => 1,
      'name' => $details['videoName'],
      'field_media_in_library' => 1,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $details['videoName'],
        'title' => $details['videoName'],
      ],
    ]);
    $media->save();

    return $media;
  }

  /**
   * Returns image field destination directory.
   *
   * @return string
   *   The destination URI to store image files.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getImageFieldDestination() {
    $image_field_config = $this->entityTypeManager
      ->getStorage('field_config')
      ->load('media.image.field_media_image');
    $field_settings = $image_field_config->getSettings();
    $destination = trim($field_settings['file_directory'], '/');
    $destination = PlainTextOutput::renderFromHtml($this->token->replace($destination, []));
    return $field_settings['uri_scheme'] . '://' . $destination;
  }

  /**
   * Builds filename out of the details array.
   *
   * @return string
   *   The built filename.
   */
  private function buildFilename($details) {
    $filename = [$details['videoName'], 'with', $details['hostName']];
    return str_replace([' ', '.'], '-', implode('-', $filename));
  }

}
