<?php

/**
 * @file
 * Contains \Drupal\govcms_ckan\Form\SettingsForm.
 *
 * The Settings Form is used to display administration configuration for the
 * module. Here the user can specify CKAN endpoints and ...
 */

namespace Drupal\govcms_ckan\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\govcms_ckan\Client\CkanClient;
use Drupal\Component\Utility\UrlHelper;

class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'govcms_ckan_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      'govcms_ckan.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('govcms_ckan.settings');

    $form['endpoint_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint Url'),
      '#description' => $this->t('Specify the endpoint url. Example https://data.gov.au (please note no trailing slash)'),
      '#weight' => 0,
      '#size' => 100,
      '#required' => TRUE,
      '#default_value' => $config->get('endpoint_url'),
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Api Key'),
      '#description' => $this->t('Specify the API key.'),
      '#weight' => 1,
      '#size' => 100,
      '#default_value' => $config->get('api_key'),
    ];

    $client = \Drupal::service('govcms.ckan');

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('govcms_ckan.settings');

    // Ensure that the given URL is valid.
    if (!UrlHelper::isValid($form_state->getValue('endpoint_url'), TRUE)) {
      $form_state->setErrorByName('endpoint_url', $this->t('The URL %url is invalid.', [
        '%url' => $form_state->getValue('endpoint_url')
      ]));
      return;
    }

    // Ensure the URL does not contain a trailing slash.
    if (\substr_compare($form_state->getValue('endpoint_url'), '/', -1, 1)) {
      $form_state->setValue('endpoint_url', \rtrim($form_state->getValue('endpoint_url'), '/'));
    }

    // If an API key is in use, enforce https.
    if (!empty($form_state->getValue('api_key'))) {
      if (\Drupal::service('file_system')->uriScheme($form_state->getValue('endpoint_url')) != 'https') {
        $form_state->setErrorByName('endpoint_url', $this->t('If using an API key, the endpoint url must use HTTPS.'));
      }
    }

    // Get a client instance.
    $response = \Drupal::service('govcms.ckan')->testConnection($form_state->getValue('endpoint_url'));

    switch ($response->getStatusCode()) {
      case 200:
        // Success! We shouldn't raise an error here.
        break;

      case 403:
        $form_state->setErrorByName('api_key', $this->t('API return "Not Authorised" please check your API key.'));
        break;

      default:
        $form_state->setErrorByName('endpoint_url', $this->t('Could not establish a connection to the endpoint. Error: @code', [
          '@code' => $response->getStatusCode(),
        ]));
        break;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = \Drupal::service('config.factory')->getEditable('govcms_ckan.settings');

    $config
      ->set('endpoint_url', $form_state->getValue('endpoint_url'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
