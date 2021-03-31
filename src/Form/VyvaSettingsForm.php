<?php

namespace Drupal\vyva\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide the settings form for Virtual Y Video automation.
 */
class VyvaSettingsForm extends ConfigFormBase {

  /**
   * The state store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return ['vyva.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vyva_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('vyva.settings');
    $form['pre_roll'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pre-roll video'),
      '#default_value' => $config->get('pre_roll'),
    ];

    $form['post_roll'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Post-roll video'),
      '#default_value' => $config->get('post_roll'),
    ];

    $form['notification'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Notification settings'),
      '#tree' => TRUE,
    ];

    $form['notification']['emails'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Emails'),
      '#description' => $this->t('Comma-separated email addresses to notify about video conversion.'),
      '#default_value' => $config->get('notification.emails'),
    ];

    $form['notification']['template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Email template'),
      '#description' => $this->t('Email template with @video_node_name and @video_node_edit_url placeholders.'),
      '#default_value' => $config->get('notification.template'),
    ];

    $form['authentication'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Authentication settings'),
      '#tree' => TRUE,
    ];

    $form['authentication']['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain'),
      '#default_value' => $config->get('authentication.domain'),
    ];

    $form['authentication']['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token'),
      '#default_value' => $config->get('authentication.token'),
    ];

    $form['webhook'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Webhook authentication'),
      '#tree' => TRUE,
    ];

    $form['webhook']['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token'),
      '#description' => $this->t('Incoming requests to VYVA REST-endpoints should contain this token.'),
      '#default_value' => $config->get('webhook.token'),
    ];

    $form['vimeo'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Vimeo settings'),
    ];

    $form['vimeo']['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $this->state->get('vyva.vimeo.client_id'),
    ];

    $form['vimeo']['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#default_value' => $this->state->get('vyva.vimeo.client_secret'),
    ];

    $form['vimeo']['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#description' => $this->t('We do not show the saved value here for security reasons.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('vyva.settings')
      ->set('pre_roll', $form_state->getValue('pre_roll'))
      ->set('post_roll', $form_state->getValue('post_roll'))
      ->set('notification', $form_state->getValue('notification'))
      ->set('authentication', $form_state->getValue('authentication'))
      ->set('webhook', $form_state->getValue('webhook'))
      ->save();

    $this->state->set('vyva.vimeo.client_id', $form_state->getValue('client_id'));
    $this->state->set('vyva.vimeo.client_secret', $form_state->getValue('client_secret'));
    if (!empty($form_state->getValue('access_token'))) {
      $this->state->set('vyva.vimeo.access_token', $form_state->getValue('access_token'));
    }

    parent::submitForm($form, $form_state);
  }

}
