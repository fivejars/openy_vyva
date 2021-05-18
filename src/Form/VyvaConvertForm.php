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
use Drupal\Core\Url;
use Drupal\vyva\VyvaManagerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\TransferException;
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
    VyvaManagerInterface $vyva_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->httpClient = $http_client;
    $this->dateFormatter = $date_formatter;
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
      // Get event data from Vimeo to use its title for video search.
      if (!$event_url = $this->entity->field_media_video_embed_field->value) {
        $media = $this->entity->getEventSeries()->field_ls_media->entity;
        $event_url = $media->field_media_video_embed_field->value;
      }
      $video = $this->vyvaManager->getVideo($event_url, $this->entity->date->value);
      $vimeo_video_id = $video ? str_replace('/videos/', '', $video['uri']) : '';
    }

    $video_data = NULL;
    if ($vimeo_video_id) {
      $video_data = $this->vyvaManager->getVimeoVideoData('https://vimeo.com/' . $vimeo_video_id);
    }

    $form['#attached']['library'] = ['vyva/convert-form'];
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
      if (!$vimeo_video_id) {
        $template = '<p>{% trans %}We couldn\'t find a video for this live stream. Please enter the video ID in the field above.{% endtrans %}</p>';
      }
      else {
        $template = '<p>{% trans %}We found the video but couldn\'t get its details. It\'s either the video is still being processed or Vimeo API is down.{% endtrans %}</p>';
      }
      $form['message'] = [
        '#type' => 'inline_template',
        '#template' => $template,
      ];
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
      '#size' => 8,
      '#maxlength' => 8,
      '#attributes' => [
        'pattern' => '[0-9]{2}:[0-9]{2}:[0-9]{2}',
      ],
      '#max' => $video_data['duration'],
      '#element_validate' => [[$this, 'validateTime']],
    ];
    $form['end_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End time'),
      '#description' => $this->t('Specify end time in HH:MM:SS format.'),
      '#default_value' => $this->dateFormatter->format($video_data['duration'], 'custom', 'H:i:s', 'UTC'),
      '#placeholder' => '00:00:00',
      '#required' => TRUE,
      '#size' => 8,
      '#maxlength' => 8,
      '#attributes' => [
        'pattern' => '[0-9]{2}:[0-9]{2}:[0-9]{2}',
      ],
      '#max' => $video_data['duration'],
      '#element_validate' => [[$this, 'validateTime']],
    ];

    $series = $this->entity->getEventSeries();
    $form['video_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Video name'),
      '#default_value' => $this->entity->label() ?: $series->title->value,
      '#required' => TRUE,
    ];

    $form['host_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Host's name"),
      '#description' => $this->t('Instructor name.'),
      '#default_value' => $this->entity->field_ls_host_name->getValue() ? $this->entity->field_ls_host_name->value : $series->field_ls_host_name->value,
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
      '#default_value' => $this->entity->field_ls_category->getValue() ? $this->entity->field_ls_category->referencedEntities() : $series->field_ls_category->referencedEntities(),
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
      '#default_value' => $this->entity->field_ls_equipment->getValue() ? $this->entity->field_ls_equipment->referencedEntities() : $series->field_ls_equipment->referencedEntities(),
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
      '#default_value' => $this->entity->field_ls_level->getValue() ? $this->entity->field_ls_level->entity : $series->field_ls_level->entity,
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
   * Validates time.
   *
   * @param array $element
   *   The form element to process.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form structure.
   */
  public static function validateTime(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = trim($element['#value']);
    $parsed = date_parse($value);
    if ($parsed['errors']) {
      $form_state
        ->setError($element, t('The time "%time" is not valid.', [
          '%time' => $value,
        ]));
    }
    $seconds = $parsed['hour'] * 3600 + $parsed['minute'] * 60 + $parsed['second'];
    if ($seconds > $element['#max']) {
      $form_state
        ->setError($element, t('The cut point must be within the original video duration.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->hasValue('begin_time')) {
      return;
    }
    if (!$form_state->hasValue('end_time')) {
      return;
    }

    $start = $form_state->getValue('begin_time');
    $end = $form_state->getValue('end_time');

    if ($start >= $end) {
      $form_state->setError($form['end_time'], $this->t('The begin cut point must be before the end cut point.'));
    }
  }

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
    $date = new DrupalDateTime($this->entity->date->value, 'UTC');

    $data = [
      'CALLBACK_URL' => $callback,
      'EVENT_INSTANCE_ID' => $this->entity->id(),
      'EVENT_DATE' => $date->getTimestamp(),
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

    try {
      $this->httpClient->request('POST', $config->get('authentication.domain'), [
        'form_params' => $data,
      ]);

      // Update conversion status.
      $this->vyvaManager->updateStatus([
        'eventinstance_id' => $this->entity->id(),
        'status' => 'requested',
      ]);
    }
    catch (TransferException $e) {
      $this->messenger()->addError($e->getMessage());
    }
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
    return Json::encode(array_column($array, 'target_id'));
  }

  /**
   * AJAX callback for changed video ID.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    // Update end time value to the default one - the default one is set to the
    // duration of the video.
    if (isset($form['end_time'])) {
      $form['end_time']['#value'] = $form['end_time']['#default_value'];
    }
    return $form;
  }

}
