<?php

namespace Drupal\vyva\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\vyva\DataManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Conversion Status resource.
 *
 * @RestResource(
 *   id = "vyva_conversion_status",
 *   label = @Translation("Virtual Y Video Automation: Conversion Status"),
 *   uri_paths = {
 *     "create" = "/vyva/api/v1/conversion-status"
 *   }
 * )
 */
class VyvaConversionStatus extends ResourceBase {

  /**
   * The entity manager.
   *
   * @var \Drupal\Vyva\DataManager
   */
  protected $dataManager;

  /**
   * Constructs a Client Data resource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Vyva\DataManagerInterface $data_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    DataManagerInterface $data_manager
  ) {
    parent::__construct($configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $logger
    );
    $this->dataManager = $data_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('openy_activity_finder'),
      $container->get('vyva.data_manager')
    );
  }

  /**
   * Handles POST request.
   *
   * @param array $data
   *   POST request data.
   */
  public function post(array $data) {
    $this->dataManager->updateStatus($data);

    return new ResourceResponse($data);
  }

}
