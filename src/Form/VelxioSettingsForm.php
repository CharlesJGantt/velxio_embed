<?php

namespace Drupal\velxio_embed\Form;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the self-hosted Velxio instance this site embeds against.
 */
class VelxioSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['velxio_embed.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'velxio_embed_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('velxio_embed.settings');

    $form['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Velxio instance base URL'),
      '#description' => $this->t('The self-hosted Velxio fork this site embeds against, e.g. https://velxio.example.com (no trailing slash).'),
      '#default_value' => $config->get('base_url'),
      '#required' => TRUE,
    ];

    $form['default_embed_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Default embed height (pixels)'),
      '#default_value' => $config->get('default_embed_height') ?: 600,
      '#min' => 300,
      '#max' => 1200,
      '#required' => TRUE,
    ];

    $form['save_to_media'] = [
      '#type' => 'details',
      '#title' => $this->t('Save to Media Library (from the Lab)'),
      '#description' => $this->t('Lets the Lab push a project straight into this site\'s media library — File menu → Save to Media Library — instead of exporting a .vlx and importing it by hand.'),
      '#open' => TRUE,
    ];

    $form['save_to_media']['token_status'] = [
      '#type' => 'item',
      '#title' => $this->t('API token status'),
      '#markup' => $config->get('save_api_token_hash')
        ? $this->t('A token is set. Generating a new one immediately invalidates it.')
        : $this->t('No token set yet — the Save to Media Library endpoint will refuse every request until one is generated.'),
    ];

    $form['save_to_media']['generate_token'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate new token'),
      '#submit' => ['::generateToken'],
      // Only this button's own action runs -- don't force base_url/height
      // to be valid just to click it.
      '#limit_validation_errors' => [],
    ];

    $form['save_to_media']['allowed_origins'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowed origins (CORS)'),
      '#description' => $this->t('One origin per line — scheme + host + port, no path (e.g. https://velxio.example.com). Only these origins can call the save endpoint from a browser.'),
      '#default_value' => implode("\n", $config->get('save_allowed_origins') ?: []),
      '#rows' => 4,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $origins = array_values(array_filter(array_map('trim', explode("\n", (string) $form_state->getValue('allowed_origins')))));
    $this->config('velxio_embed.settings')
      ->set('base_url', rtrim($form_state->getValue('base_url'), '/'))
      ->set('default_embed_height', (int) $form_state->getValue('default_embed_height'))
      ->set('save_allowed_origins', $origins)
      ->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler for the "Generate new token" button.
   *
   * Only the token's hash is ever persisted. The raw token is shown exactly
   * once, in this request's status message — if it's lost, generating a new
   * one is the only recovery, there's no way to retrieve it again.
   */
  public function generateToken(array &$form, FormStateInterface $form_state) {
    $token = Crypt::randomBytesBase64(32);
    $this->config('velxio_embed.settings')
      ->set('save_api_token_hash', Crypt::hashBase64($token))
      ->save();
    $this->messenger()->addWarning($this->t('New API token (copy it now — it will not be shown again): @token', ['@token' => $token]));
  }

}
