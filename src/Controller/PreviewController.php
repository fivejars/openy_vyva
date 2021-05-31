<?php

namespace Drupal\vyva\Controller;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\Renderer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Preview Controller.
 */
class PreviewController extends ControllerBase {

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * Constructor for Preview Controller.
   *
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $library_discovery
   *   The library discovery.
   */
  public function __construct(Renderer $renderer, LibraryDiscoveryInterface $library_discovery) {
    $this->renderer = $renderer;
    $this->libraryDiscovery = $library_discovery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('library.discovery'),
    );
  }

  /**
   * Builds the thumbnails preview page response.
   */
  public function content(Request $request) {
    $js_assets = [];
    $library = $this->libraryDiscovery->getLibraryByName('core', 'jquery');
    foreach ($library['js'] as $info) {
      $js_assets[] = file_create_url($info['data'] . '?version=' . $info['version']);
    }

    $settings = $this->config('vyva.settings')->get('thumbnails');
    foreach ($settings['cachet'] as &$value) {
      $value = file_create_url(trim($value, "\t\n\r\0\x0B\/"));
    }

    $render = [
      '#theme' => 'vyva_thumbnail',
      '#settings' => [
        'cachet' => $settings['cachet'],
        'styles' => $settings['styles'],
      ],
      '#date' => $request->get('d'),
      '#name' => $request->get('n'),
      '#level' => $request->get('l'),
      '#stylesheet' => file_create_url(drupal_get_path('module', 'vyva') . '/css/thumbnail.css'),
      '#javascript' => $js_assets,
    ];

    $html = $this->renderer->renderRoot($render);

    return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html']);
  }

}
