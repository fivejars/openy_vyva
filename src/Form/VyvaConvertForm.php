<?php

namespace Drupal\vyva\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements a Video automation conversion form.
 */
class VyvaConvertForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity ready to convert.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

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
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RouteMatchInterface $route_match,
    Messenger $messenger,
    ClientInterface $http_client,
    DateFormatterInterface $date_formatter
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->httpClient = $http_client;
    $this->dateFormatter = $date_formatter;

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
      $container->get('date.formatter')
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

    $series = $this->entity->getEventSeries();
    // TODO: identify this ID using Vimeo API.
    $vimeo_video_id = 521479164;
    $vimeo_url = 'https://vimeo.com/api/oembed.json?url=https://vimeo.com/' . $vimeo_video_id;
    $vimeo_response = $this->httpClient->request('GET', $vimeo_url);
    $video_data = Json::decode($vimeo_response->getBody()->getContents());

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

    $form['vimeo_video_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vimeo Video ID'),
      '#default_value' => $vimeo_video_id,
      '#required' => TRUE,
    ];

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

    // Might not be parseable - the proper form element is to be used.
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

}
