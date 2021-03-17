<?php

namespace Drupal\vyva\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Routing\RouteMatchInterface;
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
   * Constructs a new Vyva convert form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match, Messenger $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;

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
      $container->get('messenger')
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

    $form['video'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Video'),
      '#default_value' => 'TODO: rendered video to help identify start and end time',
    ];

    $form['begin_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Begin time'),
      '#description' => $this->t('Specify begin time in HH:MM format.'),
      '#placeholder' => '00:00',
      '#required' => TRUE,
    ];
    $form['end_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('End time'),
      '#description' => $this->t('Specify end time in HH:MM format.'),
      '#placeholder' => '00:00',
      '#required' => TRUE,
    ];

    $series = $this->entity->getEventSeries();
    $media = $series->field_ls_media->entity;
    $form['vimeo_object_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Vimeo object ID'),
      '#default_value' => $media->field_media_video_id->value,
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
    // TODO.
  }

}
