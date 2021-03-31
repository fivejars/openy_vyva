<?php

namespace Drupal\vyva\Plugin\rest\resource;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\vyva\VyvaManagerInterface;
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
   * The Virtual Y Video Automation manager.
   *
   * @var \Drupal\Vyva\VyvaManager
   */
  protected $vyvaManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

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
   * @param \Drupal\Vyva\VyvaManagerInterface $vyva_manager
   *   The Virtual Y Video Automation manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    VyvaManagerInterface $vyva_manager,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $logger
    );
    $this->vyvaManager = $vyva_manager;
    $this->configFactory = $config_factory;
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
      $container->get('vyva.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * Handles POST request.
   *
   * @param array $data
   *   POST request data.
   */
  public function post(array $data) {
    $token = $this->configFactory->get('vyva.settings')->get('webhook.token');

    if (!isset($data['token'])) {
      return new ModifiedResourceResponse(['error' => 'Token is missing'], 403);
    }
    if ($data['token'] !== $token) {
      return new ModifiedResourceResponse(['error' => 'Provided token is wrong'], 403);
    }

    $this->vyvaManager->updateStatus($data);

    return new ResourceResponse($data);
  }

}
