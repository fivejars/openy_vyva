<?php

namespace Drupal\vyva\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provide the settings form for Virtual Y Video automation.
 */
class VyvaSettingsForm extends ConfigFormBase {

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
    $form['pre_roll'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Pre-roll video'),
    ];

    $form['post_roll'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Post-roll video'),
    ];

    $form['notification'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Notification settings'),
    ];

    $form['notification']['email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Emails'),
    ];

    $form['authentication'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Authentication settings'),
    ];

    $form['authentication']['domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Domain'),
    ];

    $form['authentication']['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: add submit logic.
    $values = $form_state->getValues();
    parent::submitForm($form, $form_state);
    return $values;
  }

}
