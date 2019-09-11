<?php

namespace Drupal\simple_recaptcha;

use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Component\Serialization\Json;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;

/**
 * Provides helper service used to attach reCaptcha to forms.
 */
class SimpleReCaptchaFormManager implements ContainerInjectionInterface {

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
   * Constructs a SimpleReCaptchaFormManager object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \GuzzleHttp\ClientInterface $client
   *   Http client to connect with reCAPTCHA verify service.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   The logger factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $client, LoggerChannelFactoryInterface $logger) {
    $this->configFactory = $config_factory;
    $this->client = $client;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('http_client'),
      $container->get('logger.factory')
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
    // Check if site keys are configured, if at least one of keys isn't provided
    // protection won't work, so we can't modify and block this form.
    $config = $this->configFactory->get('simple_recaptcha.config');
    $site_key = $config->get('site_key');
    $secret_key = $config->get('secret_key');
    if(!$site_key || !$secret_key){
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

    // Check if site keys are configured, if at least one of keys isn't provided
    // protection won't work, so we can't modify and block this form.
    $config = $this->configFactory->get('simple_recaptcha.config');
    $site_key = $config->get('site_key_v3');
    $secret_key = $config->get('secret_key_v3');
    if(!$site_key || !$secret_key){
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
      '#type' => 'hidden',
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
      'response' => $form_state->getValue('simple_recaptcha_token'),
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

  }

}
