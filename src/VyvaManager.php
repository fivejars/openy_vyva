<?php

namespace Drupal\vyva;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

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
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
        $video_node = $this->createVideo($data['details']);
        $entity->gc_video = $video_node->id();
        $entity->save();

        // TODO VYVA-12: email notification.
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
    // Create media entity.
    // TODO: Put media into special directory?
    $media = $this->entityTypeManager->getStorage('media')->create([
      'bundle' => 'video',
      'uid' => 1,
      'name' => $data['videoName'],
      'field_media_in_library' => 1,
      'field_media_video_id' => $data['videoId'],
      'field_media_source' => 'vimeo',
      'field_media_video_embed_field' => 'https://vimeo.com/' . $data['videoId'],
    ]);
    $media->save();

    // Create Virtual Y Video entity.
    // TODO: add video duration field value.
    $node = $this->entityTypeManager->getStorage('node')->create([
      'type' => 'gc_video',
      'uid' => 1,
      'title' => $data['videoName'],
      'status' => NodeInterface::NOT_PUBLISHED,
      'field_gc_video_instructor' => $data['hostName'],
      'field_gc_video_category' => $data['categories'],
      'field_gc_video_equipment' => $data['equipment'],
      'field_gc_video_level' => $data['level'],
      'field_gc_video_media' => $media->id(),
    ]);
    $node->save();

    return $node;
  }

}
