<?php

namespace Drupal\simple_recaptcha;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Provides helper service used to attach reCaptcha to forms.
 */
class SimpleReCaptchaFormManager {

  use DependencySerializationTrait;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The GuzzleHttp client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $client;

  /**
   * Logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactory
   */
  protected $logger;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   *Private temp store factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * Constructs a SimpleReCaptchaFormManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \GuzzleHttp\ClientInterface $client
   *   Http client to connect with reCAPTCHA verify service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   Private temp store factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $client, LoggerChannelFactoryInterface $logger, ModuleHandlerInterface $module_handler, PrivateTempStoreFactory $temp_store_factory) {
    $this->configFactory = $config_factory;
    $this->client = $client;
    $this->logger = $logger;
    $this->moduleHandler = $module_handler;
    $this->tempStoreFactory = $temp_store_factory;
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
    // Allow modules to perform extra access checks and bypass validation.
    $bypass = FALSE;
    $this->moduleHandler->alter('simple_recaptcha_bypass', $form, $bypass);
    if ($bypass) {
      return;
    }

    // Check if site keys are configured, if at least one of keys isn't provided
    // protection won't work, so we can't modify and block this form.
    $config = $this->configFactory->get('simple_recaptcha.config');
    $site_key = $config->get('site_key');
    $secret_key = $config->get('secret_key');
    if (!$site_key || !$secret_key) {
      return;
    }

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
    $form['#attached']['drupalSettings']['simple_recaptcha']['sitekey'] = $site_key;
    $form['#attached']['drupalSettings']['simple_recaptcha']['form_ids'][$form_id] = $form_id;
    $form['#attached']['library'][] = 'simple_recaptcha/simple_recaptcha';

    $form['simple_recaptcha_token'] = [
      '#type' => 'hidden',
    ];

    $form['simple_recaptcha_type'] = [
      '#type' => 'hidden',
      '#value' => 'v2',
    ];

    $form['#validate'][] = [$this, 'validateCaptchaToken'];
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
    // Allow modules to perform extra access checks and bypass validation.
    $bypass = FALSE;
    $this->moduleHandler->alter('simple_recaptcha_bypass', $form, $bypass);
    if ($bypass) {
      return;
    }

    // Check if site keys are configured, if at least one of keys isn't provided
    // protection won't work, so we can't modify and block this form.
    $config = $this->configFactory->get('simple_recaptcha.config');
    $site_key = $config->get('site_key_v3');
    $secret_key = $config->get('secret_key_v3');
    if (!$site_key || !$secret_key) {
      return;
    }

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

    $form['#attached']['drupalSettings']['simple_recaptcha_v3']['sitekey'] = $site_key;
    $form['#attached']['drupalSettings']['simple_recaptcha_v3']['forms'][$form_id] = [
      'form_id' => $form_id,
      'score' => $configuration['v3_score'],
      'error_message' => isset($configuration['v3_error_message']) ? $configuration['v3_error_message'] : NULL,
      'action' => $configuration['recaptcha_action'],
    ];

    $form['#attached']['library'][] = 'simple_recaptcha/simple_recaptcha_v3';

    $form['simple_recaptcha_token'] = [
      '#type' => 'hidden'
    ];

    $form['simple_recaptcha_type'] = [
      '#type' => 'hidden',
      '#value' => 'v3',
    ];

    $form['simple_recaptcha_score'] = [
      '#type' => 'hidden',
      '#value' => $configuration['v3_score'],
    ];

    $form['simple_recaptcha_message'] = [
      '#type' => 'hidden',
    ];

    $form['#validate'][] = [$this, 'validateCaptchaToken'];
  }

  /**
   * Validates form with reCAPTCHA protection enabled.
   */
  public function validateCaptchaToken(&$form, FormStateInterface &$form_state) {
    // Check if valid token is already present in private temp store.
    $store = $this->tempStoreFactory->get('simple_recaptcha');
    $store_key = 'simple_recaptcha';
    $stored_token = $store->get($store_key);
    $token = $form_state->getValue('simple_recaptcha_token');

    if(strlen($token) > 0 && strlen($stored_token) > 0 && $stored_token == $token) {
      return;
    }

    $build_info = $form_state->getBuildInfo();
    $is_valid = $form_state->getValue('simple_recaptcha_validated');
    $message = $form_state->getValue('simple_recaptcha_message');
    if (!$message) {
      $message = t('There was an error during validation of your form submission, please try to reload the page and submit form again.');
    }

    $type = $form_state->getValue('simple_recaptcha_type');
    $config = $this->configFactory->get('simple_recaptcha.config');
    $config_site_key = $type == 'v2' ? $config->get('site_key') : $config->get('site_key_v3');
    $config_secret_key = $type == 'v2' ? $config->get('secret_key') : $config->get('secret_key_v3');

    // Verify reCAPTCHA token.
    $params = [
      'secret' => $config_secret_key,
      'response' => $token,
    ];

    $request = $this->client->post('https://www.google.com/recaptcha/api/siteverify', [
      'form_params' => $params,
    ]);

    $api_response = Json::decode($request->getBody()->getContents());
    if (!$api_response['success']) {
      $this->logger->get('simple_recaptcha')->notice(t('reCAPTCHA validation failed, error codes: @errors', ['@errors' => implode(',', $api_response['error-codes'])]));
      $form_state->setError($form, $message);
    }

    // Verify score for reCAPTCHA v3.
    if ($type == 'v3' && isset($api_response['score'])) {
      $desired_score = $form_state->getValue('simple_recaptcha_score');
      $api_score = $api_response['score'] * 100;

      if ($api_score < $desired_score) {
        $this->logger->get('simple_recaptcha')->notice(t('reCAPTCHA validation failed, reCAPTCHA score too low: @score (desired score was @desired_score)', ['@score' => $api_score, '@desired_score' => $desired_score]));
        $form_state->setError($form, $message);
      }
    }

    // If API response is valid, store current token in private temp store,
    // so we won't have to validate this form again.
    if($api_response['success']) {
      $store->set($store_key, $token);
    }
  }
}
