<?php

namespace Drupal\simple_recaptcha\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides administration form for simple_recaptcha module.
 */
class SimpleRecaptchaSettingsForm extends ConfigFormBase {

  const SETTINGS = 'simple_recaptcha.config';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simple_recapcha_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['keys_v2'] = [
      '#type' => 'details',
      '#title' => $this->t('reCAPTCHA v2 checkbox'),
      '#open' => TRUE,
    ];

    $form['keys_v2']['site_key'] = [
      '#title' => $this->t('Site key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('site_key'),
      '#description' => $this->t('reCaptcha site key will be used in the HTML/JS code to render and handle reCaptcha widget.'),
    ];

    $form['keys_v2']['secret_key'] = [
      '#title' => $this->t('Secret key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('secret_key'),
      '#description' => $this->t('Secret key will be used internally to connect with reCaptcha API and verify responses.'),
    ];

    $form['keys_v3'] = [
      '#type' => 'details',
      '#title' => $this->t('reCAPTCHA v3 (invisible)'),
      '#open' => TRUE,
    ];

    $form['keys_v3']['site_key_v3'] = [
      '#title' => $this->t('Site key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('site_key_v3'),
      '#description' => $this->t('reCaptcha site key will be used in the HTML/JS code to render and handle reCaptcha widget.'),
    ];

    $form['keys_v3']['secret_key_v3'] = [
      '#title' => $this->t('Secret key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('secret_key_v3'),
      '#description' => $this->t('Secret key will be used internally to connect with reCaptcha API and verify responses.'),
    ];

    $form['form_ids'] = [
      '#type' => 'textarea',
      '#description' => $this->t('Add comma separated list of form ids, e.g.: user_login_form,user_pass,user_register_form.'),
      '#title' => $this->t('Form IDs'),
      '#default_value' => $config->get('form_ids'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('form_ids', $form_state->getValue('form_ids'))
      ->set('site_key', $form_state->getValue('site_key'))
      ->set('secret_key', $form_state->getValue('secret_key'))
      ->set('site_key_v3', $form_state->getValue('site_key_v3'))
      ->set('secret_key_v3', $form_state->getValue('secret_key_v3'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
