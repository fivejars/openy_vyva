<?php

namespace Drupal\vyva\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for Video conversion routes.
 */
class RouteSubscriber extends RouteSubscriberBase {

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
  protected function alterRoutes(RouteCollection $collection) {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($route = $this->getVyvaConvertRoute($entity_type)) {
        $collection->add("entity.$entity_type_id.vyva_convert_form", $route);
      }
    }
  }

  /**
   * Gets the convert route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getVyvaConvertRoute(EntityTypeInterface $entity_type) {
    if ($convert_form = $entity_type->getLinkTemplate('vyva-convert-form')) {
      $entity_type_id = $entity_type->id();
      $route = new Route($convert_form);
      $route
        ->addDefaults([
          '_form' => '\Drupal\vyva\Form\VyvaConvertForm',
          '_title' => 'Convert ' . $entity_type->getLabel(),
        ])
        ->addRequirements([
          '_entity_access' => $entity_type_id . '.vyva-convert',
        ])
        ->setOption('_vyva_entity_type_id', $entity_type_id)
        ->setOption('_admin_route', TRUE)
        ->setOption('parameters', [
          $entity_type_id => ['type' => 'entity:' . $entity_type_id],
        ]);

      return $route;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = 'onAlterRoutes';
    return $events;
  }

}
