<?php

namespace Drupal\vyva\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\vyva\VyvaManagerInterface;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a Video automation conversion form.
 */
class VyvaConvertForm extends FormBase {

  /**
   * The entity ready to convert.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * An http client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The Virtual Y Video Automation manager.
   *
   * @var \Drupal\Vyva\VyvaManager
   */
  protected $vyvaManager;

  /**
   * Constructs a new Vyva convert form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   An HTTP client.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state store.
   * @param \Drupal\Vyva\VyvaManagerInterface $vyva_manager
   *   The Virtual Y Video Automation manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $route_match,
    Messenger $messenger,
    ClientInterface $http_client,
    DateFormatterInterface $date_formatter,
    StateInterface $state,
    VyvaManagerInterface $vyva_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->httpClient = $http_client;
    $this->dateFormatter = $date_formatter;
    $this->state = $state;
    $this->vyvaManager = $vyva_manager;

    $parameter_name = $route_match->getRouteObject()->getOption('_vyva_entity_type_id');
    $this->entity = $route_match->getParameter($parameter_name);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('messenger'),
      $container->get('http_client'),
      $container->get('date.formatter'),
      $container->get('state'),
      $container->get('vyva.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vyva_convert_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!$this->entity) {
      return $form;
    }

    // Check if form was submitted by AJAX with Vimeo Video ID.
    $vimeo_video_id = $form_state->getValue('vimeo_video_id');
    if (!$vimeo_video_id) {
      $video = $this->getVideo();
      $vimeo_video_id = $video ? str_replace('/videos/', '', $video['uri']) : '';
    }

    $video_data = NULL;
    if ($vimeo_video_id) {
      $vimeo_url = 'https://vimeo.com/' . $vimeo_video_id;
      $response = $this->httpClient->request('GET', 'https://vimeo.com/api/oembed.json?url=' . $vimeo_url);
      $video_data = Json::decode($response->getBody()->getContents());
    }

    $form['#prefix'] = '<div id="ajax-wrapper">';
    $form['#suffix'] = '</div>';
    $form['vimeo_video_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vimeo Video ID'),
      '#description' => $this->t('ID of the video found by API call; might be empty if the search failed.'),
      '#default_value' => $vimeo_video_id,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::ajaxCallback',
        'event' => 'change',
        'wrapper' => 'ajax-wrapper',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Updating video information...'),
        ],
      ],
    ];

    if (!$video_data) {
      return $form;
    }

    $form['video'] = [
      '#type' => 'inline_template',
      '#template' => $video_data['html'],
    ];

    $form['begin_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Begin time'),
      '#description' => $this->t('Specify begin time in HH:MM:SS format.'),
      '#default_value' => '00:00:00',
      '#placeholder' => '00:00:00',
      '#required' => TRUE,
    ];
    $form['end_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End time'),
      '#description' => $this->t('Specify end time in HH:MM:SS format.'),
      '#default_value' => $this->dateFormatter->format($video_data['duration'], 'custom', 'H:i:s', 'UTC'),
      '#placeholder' => '00:00:00',
      '#required' => TRUE,
    ];

    $series = $this->entity->getEventSeries();
    $form['video_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Video name'),
      '#default_value' => $series->title->value,
      '#required' => TRUE,
    ];

    $form['host_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Host's name"),
      '#description' => $this->t('Instructor name.'),
      '#default_value' => $series->field_ls_host_name->value,
      '#required' => TRUE,
    ];

    $form['categories'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Categories'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_settings' => [
        'target_bundles' => ['gc_category'],
      ],
      '#size' => 100,
      '#maxlength' => 512,
      '#default_value' => $series->field_ls_category->referencedEntities(),
    ];

    $form['equipment'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Equipment'),
      '#target_type' => 'taxonomy_term',
      '#tags' => TRUE,
      '#selection_settings' => [
        'target_bundles' => ['gc_equipment'],
      ],
      '#size' => 100,
      '#maxlength' => 512,
      '#default_value' => $series->field_ls_equipment->referencedEntities(),
    ];

    $form['level'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Level'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'target_bundles' => ['gc_level'],
      ],
      '#size' => 100,
      '#maxlength' => 512,
      '#default_value' => $series->field_ls_level->entity,
    ];

    $form['image'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image'),
      '#default_value' => 'TODO',
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['convert'] = [
      '#type' => 'submit',
      '#value' => $this->t('Convert'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('vyva.settings');
    $options = ['absolute' => TRUE];
    $callback = Url::fromUri('internal:/vyva/api/v1/conversion-status', $options);
    $callback = $callback->toString();

    // Might not be parsable - the proper form element is to be used.
    $parsed = date_parse($form_state->getValue('begin_time'));
    $start = $parsed['hour'] * 3600 + $parsed['minute'] * 60 + $parsed['second'];
    $parsed = date_parse($form_state->getValue('end_time'));
    $end = $parsed['hour'] * 3600 + $parsed['minute'] * 60 + $parsed['second'];
    $duration = $end - $start;

    $categories = $form_state->getValue('categories');
    $equipment = $form_state->getValue('equipment');

    $data = [
      'CALLBACK_URL' => $callback,
      'EVENT_INSTANCE_ID' => $this->entity->id(),
      'VIMEO_VIDEO_ID' => $form_state->getValue('vimeo_video_id'),
      'START' => $start,
      'DURATION' => $duration,
      'VIDEO_NAME' => $form_state->getValue('video_name'),
      'VY_HOST_NAME' => $form_state->getValue('host_name'),
      'VY_CATEGORIES' => $this->formatArrayValues($categories),
      'VY_EQUIPMENT' => $this->formatArrayValues($equipment),
      'VY_LEVEL' => $form_state->getValue('level'),
      'PREROLL_VIMEO_VIDEO_ID' => $config->get('pre_roll'),
      'POSTROLL_VIMEO_VIDEO_ID' => $config->get('post_roll'),
    ];

    $uri = $config->get('authentication.domain');

    $this->httpClient->post($uri, [
      'form_params' => $data,
    ]);

    // Update conversion status.
    $this->vyvaManager->updateStatus([
      'eventinstance_id' => $this->entity->id(),
      'status' => 'requested',
    ]);
  }

  /**
   * Formats an array so that it can be transferred.
   *
   * @param mixed $array
   *   The array of values.
   *
   * @return mixed
   *   The formatted value.
   */
  private function formatArrayValues($array) {
    if (!is_array($array)) {
      $array = [$array];
    }
    return json_encode(array_column($array, 'target_id'));
  }

  /**
   * Get video data from Vimeo.
   *
   * @return array|null
   *   Video data or null.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getVideo() {
    $video = NULL;
    $series = $this->entity->getEventSeries();
    // Get event data from Vimeo to use its title for video search.
    $media = $series->field_ls_media->entity;
    $event_url = $media->field_media_video_embed_field->value;
    if (!$event_url) {
      return NULL;
    }
    $response = $this->httpClient->request('GET', 'https://vimeo.com/api/oembed.json?url=' . $event_url);
    $event_data = Json::decode($response->getBody()->getContents());

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
    $date = new DrupalDateTime($this->entity->date->value, 'UTC');
    foreach ($items['data'] as $item) {
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

  /**
   * AJAX callback for changed video ID.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    // Update end time value to the default one - the default one is set to the
    // duration of the video.
    $form['end_time']['#value'] = $form['end_time']['#default_value'];
    return $form;
  }

}
