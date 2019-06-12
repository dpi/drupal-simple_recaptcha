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
    $form['site_key'] = [
      '#title' => $this->t('Site key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('site_key'),
      '#description' => $this->t('reCaptcha site key will be used in the HTML/JS code to render and handle reCaptcha widget.'),
    ];
    $form['secret_key'] = [
      '#title' => $this->t('Secret key'),
      '#type' => 'textfield',
      '#default_value' => $config->get('secret_key'),
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
      ->save();

    parent::submitForm($form, $form_state);
  }

}
