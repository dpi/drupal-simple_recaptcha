<?php

namespace Drupal\simple_recaptcha_webform\Plugin\WebformHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\simple_recaptcha\SimpleReCaptchaFormManager;

/**
 * Webform submission handler plugin.
 *
 * @WebformHandler(
 *   id = "simple_recaptcha",
 *   label = @Translation("reCAPTCHA"),
 *   category = @Translation("simple_recaptcha"),
 *   description = @Translation("Adds reCaptcha protection to the webform."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class SimpleRecaptchaWebformHandler extends WebformHandlerBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Webform submission storage.
   *
   * @var \Drupal\webform\WebformSubmissionStorageInterface
   */
  protected $submissionStorage;

  /**
   * The webform submission (server-side) conditions (#states) validator.
   *
   * @var \Drupal\webform\WebformSubmissionConditionsValidator
   */
  protected $conditionsValidator;

  /**
   * Current Route.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRoute;

  /**
   * Current active user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Form manager service from simple_recaptcha module.
   *
   * @var \Drupal\simple_recaptcha\SimpleReCaptchaFormManager
   */
  protected $reCaptchaFormManager;

  /**
   * Constructs an ActivityWebformHandler object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\webform\WebformSubmissionConditionsValidatorInterface $conditions_validator
   *   The webform submission conditions (#states) validator.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route
   *   Current route.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current active user.
   * @param \Drupal\simple_recaptcha\SimpleReCaptchaFormManager $recaptcha_form_manager
   *   Helper service used to attach reCaptcha to forms.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, WebformSubmissionConditionsValidatorInterface $conditions_validator, CurrentRouteMatch $current_route, AccountProxyInterface $current_user, SimpleReCaptchaFormManager $recaptcha_form_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $entity_type_manager, $conditions_validator);
    $this->currentRoute = $current_route;
    $this->currentUser = $current_user;
    $this->reCaptchaFormManager = $recaptcha_form_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('webform_submission.conditions_validator'),
      $container->get('current_route_match'),
      $container->get('current_user'),
      $container->get('simple_recaptcha.form_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'recaptcha_type' => 'v2',
      'v3_score' => 90,
      'v3_error_message' => 'There was an error during validation of your form submission, please try to reload the page and submit form again.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['recaptcha'] = [
      '#type' => 'details',
      '#title' => $this->t('Handler settings'),
      '#open' => TRUE,
    ];

    $form['recaptcha']['recaptcha_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('reCAPTCHA type'),
      '#options' => [
        'v2' => $this->t('reCAPTCHA v2 (checkbox)'),
        'v3' => $this->t('reCAPTCHA v3 (invisible)'),
      ],
      '#default_value' => $this->configuration['recaptcha_type'],
    ];

    $form['recaptcha']['v3_score'] = [
      '#type' => 'number',
      '#title' => $this->t('desired reCAPTCHA score'),
      '#max' => 100,
      '#min' => 1,
      '#default_value' => $this->configuration['v3_score'],
      '#states' => [
        'visible' => [
          ':input[name="settings[recaptcha][recaptcha_type]"]' => ['value' => 'v3'],
        ],
      ],
      '#description' => $this->t('reCAPTCHA v3 returns a score (100 is very likely a good interaction, 0 is very likely a bot). Based on the score, you can decide when to block form submissions.'),
    ];

    $form['recaptcha']['v3_error_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom error message'),
      '#default_value' => $this->configuration['v3_error_message'],
      '#states' => [
        'visible' => [
          ':input[name="settings[recaptcha][recaptcha_type]"]' => ['value' => 'v3'],
        ],
      ],
      '#description' => $this->t('This error message will be shown when reCAPTCHA validation will fail.'),
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['recaptcha_type'] = $values['recaptcha']['recaptcha_type'];
    $this->configuration['v3_score'] = $values['recaptcha']['v3_score'];
    $this->configuration['v3_error_message'] = $values['recaptcha']['v3_error_message'];
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // Skip for users with bypass permission.
    if ($this->currentUser->hasPermission('bypass simple_recaptcha')) {
      return;
    }

    $configuration = $this->getConfiguration();

    $info = $form_state->getBuildInfo();
    switch ($configuration['settings']['recaptcha_type']) {
      case 'v3':
        $settings = $configuration['settings'];
        $settings['recaptcha_action'] = $this->getWebform()->id();
        $this->reCaptchaFormManager->addReCaptchaInvisible($form, $info['form_id'], $settings);
        break;

      case 'v2':
      default:
        $this->reCaptchaFormManager->addReCaptchaChechbox($form, $info['form_id']);
        break;
    }

    return $form;
  }

}
