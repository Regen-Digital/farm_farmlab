<?php

namespace Drupal\farm_farmlab\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * FarmLab settings form.
 */
class FarmLabServerSettingsForm extends ConfigFormBase {

  /**
   * Config settings key.
   *
   * @var string
   */
  const SETTINGS = 'farm_farmlab.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_farmlab_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config(static::SETTINGS);

    // API URL.
    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FarmLab API server URL'),
      '#description' => $this->t('The FarmLab API server URL.'),
      '#default_value' => $config->get('api_url'),
      '#required' => TRUE,
    ];

    // Auth URL.
    $form['auth_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FarmLab authentication server URL'),
      '#description' => $this->t('The FarmLab authentication server URL. This is used to build Authorization links.'),
      '#default_value' => $config->get('auth_url'),
      '#required' => TRUE,
    ];

    // Client ID.
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FarmLab OAuth client ID'),
      '#description' => $this->t('The OAuth client ID used to authenticate with FarmLab.'),
      '#default_value' => $config->get('client_id'),
      '#required' => TRUE,
    ];

    // Client secret.
    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FarmLab OAuth client secret (optional)'),
      '#description' => $this->t('An optional OAuth client secret used to authenticate with FarmLab.'),
      '#default_value' => $config->get('client_secret'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Ensure the api_url is an absolute path so it can be used as a base_uri.
    $api_url = $form_state->getValue('api_url');
    $api_url = trim($api_url, '/') . "/";

    $this->configFactory->getEditable(static::SETTINGS)
      ->set('api_url', $api_url)
      ->set('auth_url', $form_state->getValue('auth_url'))
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('client_secret', $form_state->getValue('client_secret'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
