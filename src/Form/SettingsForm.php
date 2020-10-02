<?php

namespace Drupal\dx_messages\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure DX Messages settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dx_messages_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['dx_api_create_endpoint'] = [
      '#type' => 'url',
      '#maxlength' => 1024,
      '#title' => $this->t('Messages Create Endpoint'),
      '#default_value' => $this->config('dx_messages.settings')
        ->get('dx_api_create_endpoint'),
    ];
    $form['dx_api_messages_endpoint'] = [
      '#type' => 'url',
      '#maxlength' => 1024,
      '#title' => $this->t('Message Status Endpoint'),
      '#default_value' => $this->config('dx_messages.settings')
        ->get('dx_api_messages_endpoint'),
    ];
    $form['dx_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Messages Endpoint Key'),
      '#default_value' => $this->config('dx_messages.settings')
        ->get('dx_api_key'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('dx_messages.settings')
      ->set('dx_api_create_endpoint', $form_state->getValue('dx_api_create_endpoint'))
      ->set('dx_api_messages_endpoint', $form_state->getValue('dx_api_messages_endpoint'))
      ->set('dx_api_key', $form_state->getValue('dx_api_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['dx_messages.settings'];
  }

}
