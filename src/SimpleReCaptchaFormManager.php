<?php

namespace Drupal\simple_recaptcha;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides helper service used to attach reCaptcha to forms.
 */
class SimpleReCaptchaFormManager implements ContainerInjectionInterface {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a SimpleReCaptchaFormManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Add reCaptcha v2 container and libraries to the form.
   *
   * @param array $form
   *   Renderable array of form which will be secured by reCaptcha checkbox.
   * @param string $form_id
   *   Form ID of form which will be secured.
   */
  public function addReCaptchaChechbox(array &$form, $form_id) {
    $config = $this->configFactory->get('simple_recaptcha.config');
    // Add HTML data attributes and Wrapper for reCAPTCHA widget.
    $form['#attributes']['data-recaptcha-id'] = $form_id;
    $form['actions']['captcha'] = [
      '#type' => 'container',
      '#weight' => -1,
      '#attributes' => [
        'id' => $form_id . '-captcha',
        'class' => ['recaptcha', 'recaptcha-wrapper'],
      ],
    ];

    // Attach helper libraries.
    $form['#attached']['drupalSettings']['simple_recaptcha']['sitekey'] = $config->get('site_key');
    $form['#attached']['drupalSettings']['simple_recaptcha']['form_ids'][$form_id] = $form_id;
    $form['#attached']['library'][] = 'simple_recaptcha/simple_recaptcha';
  }

  /**
   * Add reCaptcha v3 container and libraries to the form.
   *
   * @param array $form
   *   Renderable array of form which will be secured by reCaptcha checkbox.
   * @param string $form_id
   *   Form ID of form which will be secured.
   * @param array $configuration
   *   Configuration for invisible recaptcha.
   */
  public function addReCaptchaInvisible(array &$form, $form_id, array $configuration) {

    $config = $this->configFactory->get('simple_recaptcha.config');
    // Add HTML data attributes and Wrapper for reCAPTCHA widget.
    $form['#attributes']['data-recaptcha-id'] = $form_id;
    $form['actions']['captcha'] = [
      '#type' => 'container',
      '#weight' => -1,
      '#attributes' => [
        'id' => $form_id . '-captcha',
        'class' => ['recaptcha-v3', 'recaptcha-v3-wrapper'],
      ],
    ];

    $form['#attached']['drupalSettings']['simple_recaptcha_v3']['sitekey'] = $config->get('site_key_v3');
    $form['#attached']['drupalSettings']['simple_recaptcha_v3']['forms'][$form_id] = [
      'form_id' => $form_id,
      'score' => $configuration['v3_score'],
      'error_message' => $configuration['v3_error_message'],
      'action' => $configuration['recaptcha_action']
    ];
    $form['#attached']['library'][] = 'simple_recaptcha/simple_recaptcha_v3';
  }

}
