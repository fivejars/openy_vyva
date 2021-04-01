<?php

namespace Drupal\vyva;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;

/**
 * Subscriber for Video conversion routes.
 */
class VyvaManager implements VyvaManagerInterface {

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
   * The currently logged-in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Constructs a new VyvaManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   Mail manager service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    AccountInterface $user,
    MailManagerInterface $mail_manager,
    ClientInterface $http_client
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->currentUser = $user;
    $this->mailManager = $mail_manager;
    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public function updateStatus($data) {
    $entity = $this->getEntity($data['eventinstance_id']);

    $entity->conversion_status = $data['status'];
    $entity->save();

    switch ($data['status']) {
      case 'progress':
        // TODO: save progress details.
        break;

      case 'failure':
        // TODO: save failure details.
        break;

      case 'completed':
        // Create Virtual Y Video node.
        $video_node = $this->createVideo($data);
        $entity->gc_video = $video_node->id();
        $entity->save();

        // Send email notification.
        $module = 'vyva';
        $key = 'create_gc_video';
        $to = $this->configFactory->get('vyva.settings')->get('notification.emails');
        $langcode = $this->currentUser->getPreferredLangcode();
        $params['message'] = $this->configFactory->get('vyva.settings')->get('notification.template');
        $params['video_node_name'] = $video_node->label();
        $params['video_node_edit_url'] = $video_node->toUrl('edit-form')->setAbsolute()->toString();

        $this->mailManager->mail($module, $key, $to, $langcode, $params);
        break;

      case 'started':
      case 'requested':
      default:
        break;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity($eventinstance_id) {
    $entities = $this->entityTypeManager->getStorage('vyva_conversion_status')->loadByProperties(
      ['eventinstance' => $eventinstance_id]
    );

    if (!empty($entities)) {
      return reset($entities);
    }

    $entity = $this->entityTypeManager->getStorage('vyva_conversion_status')->create(
      ['eventinstance' => $eventinstance_id]
    );
    $entity->save();

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function createVideo($data) {
    $eventinstance = $this->entityTypeManager
      ->getStorage('eventinstance')
      ->load($data['eventinstance_id']);
    $series = $eventinstance->getEventSeries();
    $details = $data['details'];

    // Create media entity.
    // TODO: Put media into special directory?
    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => 'video',
      'uid' => 1,
      'name' => $details['videoName'],
      'field_media_in_library' => 1,
      'field_media_video_id' => $details['videoId'],
      'field_media_source' => 'vimeo',
      'field_media_video_embed_field' => 'https://vimeo.com/' . $details['videoId'],
    ]);
    $media->save();

    // Get video data from Vimeo.
    $video_data = $this->getVimeoVideoData('https://vimeo.com/' . $details['videoId']);

    // Create Virtual Y Video entity.
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'gc_video',
      'uid' => 1,
      'title' => $details['videoName'],
      'status' => NodeInterface::NOT_PUBLISHED,
      'field_gc_video_instructor' => $details['hostName'],
      'field_gc_video_category' => $details['categories'],
      'field_gc_video_equipment' => $details['equipment'],
      'field_gc_video_level' => $details['level'],
      'field_gc_video_media' => $media->id(),
      'field_gc_video_description' => $eventinstance->body->isEmpty() ? $series->body : $eventinstance->body,
      'field_gc_video_duration' => $video_data['duration'],
    ]);
    $node->save();

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getVimeoVideoData($vimeo_url) {
    $response = $this->httpClient->request('GET', 'https://vimeo.com/api/oembed.json?url=' . $vimeo_url);
    return Json::decode($response->getBody()->getContents());
  }

}
