<?php

namespace Drupal\vyva;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Subscriber for Video conversion routes.
 */
class DataManager implements DataManagerInterface {

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

    switch ($data['status']) {
      case 'started':
      case 'progress':
      case 'failure':
        $entity->conversion_status = $data['status'];
        $entity->save();
        break;

      case 'completed':
        $entity->conversion_status = $data['status'];
        $entity->save();
        // TODO VYVA-13: create Virtual Y Video node.
        // TODO VYVA-12: email notification.
        break;

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

}
