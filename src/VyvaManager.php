<?php

namespace Drupal\vyva;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;

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
   * The state store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The VYVA media manager.
   *
   * @var \Drupal\vyva\VyvaMediaManager
   */
  protected $mediaManager;

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
   * @param \Drupal\Core\State\StateInterface $state
   *   The state store.
   * @param \Drupal\vyva\VyvaMediaManager $vyva_media_manager
   *   The VYVA media manager.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    AccountInterface $user,
    MailManagerInterface $mail_manager,
    ClientInterface $http_client,
    StateInterface $state,
    VyvaMediaManager $vyva_media_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->currentUser = $user;
    $this->mailManager = $mail_manager;
    $this->httpClient = $http_client;
    $this->state = $state;
    $this->mediaManager = $vyva_media_manager;
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
      case 'failure':
        $entity->details = $data['details'];
        $entity->save();
        break;

      case 'completed':
        // Create Virtual Y Video node.
        $video_node = $this->createVideo($data);
        $entity->gc_video = $video_node->id();
        $entity->details = '';
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
    $entities = $this->entityTypeManager
      ->getStorage('vyva_conversion_status')
      ->loadByProperties(['eventinstance' => $eventinstance_id]);

    if (!empty($entities)) {
      return reset($entities);
    }

    $entity = $this->entityTypeManager
      ->getStorage('vyva_conversion_status')
      ->create(['eventinstance' => $eventinstance_id]);
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

    $teaser = $this->mediaManager->prepareThumbnailMedia($details);

    // Create Virtual Y Video entity.
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'gc_video',
      'uid' => 1,
      'title' => $details['videoName'],
      'status' => NodeInterface::NOT_PUBLISHED,
      'created' => $details['videoDate'],
      'field_gc_video_instructor' => $details['hostName'],
      'field_gc_video_category' => $details['categories'],
      'field_gc_video_equipment' => $details['equipment'],
      'field_gc_video_level' => $details['level'],
      'field_gc_video_image' => $teaser ? $teaser->id() : [],
      'field_gc_video_media' => $media->id(),
      'field_gc_video_description' => $eventinstance->body->isEmpty() ? $series->body : $eventinstance->body,
      'field_gc_video_duration' => $details['duration'],
    ]);
    $node->save();

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getVimeoVideoData($vimeo_url) {
    try {
      $response = $this->httpClient->request('GET', 'https://vimeo.com/api/oembed.json', [
        'query' => ['url' => $vimeo_url],
      ]);
      return Json::decode($response->getBody()->getContents());
    }
    catch (TransferException $e) {
      watchdog_exception('vyva', $e);
    }
    return NULL;
  }

  /**
   * Get video data from Vimeo.
   *
   * @param string $event_url
   *   The Vimeo event URL.
   * @param string $date
   *   The Vimeo event date.
   *
   * @return array|null
   *   Video data or null.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getVideo($event_url, $date) {
    $video = NULL;
    // Get event data from Vimeo to use its title for video search.
    if (!$event_url) {
      return NULL;
    }
    if (!$event_data = $this->getVimeoVideoData($event_url)) {
      return NULL;
    }

    // Send videos search request.
    $response = $this->httpClient->request('GET', 'https://api.vimeo.com/me/videos', [
      'headers' => [
        'Authorization' => 'Bearer ' . $this->state->get('vyva.vimeo.access_token'),
      ],
      'query' => [
        'query' => $event_data['title'],
        'per_page' => 100,
        'sort' => 'date',
        'direction' => 'desc',
      ],
    ]);
    $items = Json::decode($response->getBody()->getContents());

    // It looks like Vimeo creates new video for the next event occurrence once
    // the previous occurrence ended. That is why we need to find the first
    // item with created date less than this eventinstance date.
    $date = new DrupalDateTime($date, 'UTC');
    foreach ($items['data'] as $item) {
      if (empty($item['app']['name']) || $item['app']['name'] != 'Vimeo Live') {
        continue;
      }
      $item_date = new DrupalDateTime($item['created_time'], 'UTC');
      if ($item_date > $date) {
        continue;
      }
      else {
        $video = $item;
        break;
      }
    }

    return $video;
  }

}
