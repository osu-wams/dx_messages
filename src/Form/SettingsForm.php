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
      '#description' => $this->t('Endpoint to Create new messages.'),
      '#default_value' => $this->config('dx_messages.settings')
        ->get('dx_api_create_endpoint'),
    ];
    $form['dx_api_messages_endpoint'] = [
      '#type' => 'url',
      '#maxlength' => 1024,
      '#title' => $this->t('Message Status Endpoint'),
      '#description' => $this->t('Endpoint for a given message. The Message ID will be stored with each node.'),
      '#default_value' => $this->config('dx_messages.settings')
        ->get('dx_api_messages_endpoint'),
    ];
    $form['dx_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Messages Endpoint Key'),
      '#description' => $this->t('Your API key. This should not be stored here, instead look into secrets management for your hosing provider.'),
      '#default_value' => $this->config('dx_messages.settings')
        ->get('dx_api_key'),
    ];
    return parent::buildForm($form, $form_state);
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
