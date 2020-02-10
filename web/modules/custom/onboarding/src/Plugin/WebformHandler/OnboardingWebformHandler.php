<?php


namespace Drupal\onboarding\Plugin\WebformHandler;


use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\onboarding\OnboardingManagerInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Plugin\WebformElementManagerInterface;
use Drupal\webform\Plugin\WebformHandler\EmailWebformHandler;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformThemeManagerInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

/**
 * Administration webform that initiates the onboarding process.
 *
 * A new user is created and a webform submission's email is scheduled with
 * further information of the onboarding process for the candidate.
 *
 * @WebformHandler(
 *   id = "onboarding_process",
 *   label = @Translation("Onboarding process"),
 *   category = @Translation("Notification"),
 *   description = @Translation("Sends a scheduled email to a potential customer containing a link, that starts the onboarding process."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class OnboardingWebformHandler extends EmailWebformHandler {

  /**
   * The onboarding service
   *
   * @var \Drupal\onboarding\OnboardingManagerInterface
   */
  protected $onboarding_manager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, WebformSubmissionConditionsValidatorInterface $conditions_validator, AccountInterface $current_user, ModuleHandlerInterface $module_handler, LanguageManagerInterface $language_manager, MailManagerInterface $mail_manager, WebformThemeManagerInterface $theme_manager, WebformTokenManagerInterface $token_manager, WebformElementManagerInterface $element_manager, OnboardingManagerInterface $onboarding_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $entity_type_manager, $conditions_validator, $current_user, $module_handler, $language_manager, $mail_manager, $theme_manager, $token_manager, $element_manager);
    $this->onboarding_manager =  $onboarding_manager;
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
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('language_manager'),
      $container->get('plugin.manager.mail'),
      $container->get('webform.theme_manager'),
      $container->get('webform.token_manager'),
      $container->get('plugin.manager.webform.element'),
      $container->get('onboarding.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'steps' => [
        'step_0' => 'none',
        'step_1' => 'none',
        'step_2' => 'none',
        'step_3' => 'none',
        'step_4' => 'none',
      ]
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $summary = parent::getSummary();

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // webform selection options
    $webform_options = ['none' => $this->t('None')];
    $webform_ids = \Drupal::entityQuery('webform')
      ->condition('category', $this->onboarding_manager::ONBOARDING_STEP_WEBFORM_CATEGORY)
      ->execute();
    foreach($webform_ids as $webform_id) {
      /** @var  \Drupal\webform\Entity\Webform $webform */
      $webform = Webform::load($webform_id);
      $webform_options[$webform_id] = $webform->get('title');
    }

    //
    // create onboarding form elements
    $form['onboarding'] = [
      '#type' => 'details',
      '#title' => $this->t('Onboarding process'),
      '#open' => TRUE,
      '#description' => $this->t('Select a webform for each onboarding step.')
    ];

    $onboarding_steps = $this->onboarding_manager->getOnboardingStepDefinitions();
    foreach ($onboarding_steps as $key => $title) {
      $form['onboarding']['steps'][$key] = [
        '#type' => 'select',
        '#title' => $title,
        '#options' => $webform_options,
        '#default_value' => $this->configuration['steps'][$key],
      ];
    }

    // return enhanced form
    $form = parent::buildConfigurationForm($form, $form_state);
    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    // save onboarding steps configuration
    $values = $form_state->getValues();
    $this->configuration['steps'] = $values['onboarding']['steps'];
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    /* @var \Drupal\onboarding\OnboardingManager $onboarding_manager */
    $onboarding_manager = \Drupal::service('onboarding.manager');
    $timestamp = \Drupal::time()->getRequestTime();

    //
    // create user account
    $name = $webform_submission->getElementData('name');
    $email = $webform_submission->getElementData('email');
    $amount = $webform_submission->getElementData('amount');
    $lang_code = $this->languageManager->getCurrentLanguage()->getId();
    $account = $onboarding_manager->createAccount($name, $email, $amount, $lang_code);
    if ($account) {
      // add user specific onboarding url
      $userOnboardinglink = $onboarding_manager->createUserOnboardingUrl($account, $timestamp, ['absolute' => TRUE])->toString();
      $account->set("field_onboarding_link", $userOnboardinglink);
      try {
        $account->save();
      }
      catch (Exception $e) {
        // send a message to the screen
        $t_args = ['%user' => $name, '%email' => $email];
        $this->messenger()->addError($this->t('The on-boarding link for the account %user (%email) could not be updated.', $t_args));
        return;
      }
    }
    else {
      // send a message to the screen
      $t_args = ['%user' => $name, '%email' => $email];
      $this->messenger()->addError($this->t('The account for %user (%email) could not be created. For more information refer to the log messages.', $t_args));
      return;
    }

    //
    // Prepare message body to be sent
    // First: replace button tokens in body with placeholders, then get tokenized message
    // Second: replace placeholders in tokenized message body with button markup and send message
    $webform_submission->onboarding_user = $account;
    $state = $webform_submission->getWebform()->getSetting('results_disabled') ? WebformSubmissionInterface::STATE_COMPLETED : $webform_submission->getState();
    if ($this->configuration['states'] && in_array($state, $this->configuration['states'])) {
      $button_tokens = $this->replaceButtonTokens($this->configuration['body'], $webform_submission);
      $message = $this->getMessage($webform_submission);

      // replace button tokens in message body and send message
      $body  = $message['body']->__toString();
      foreach($button_tokens as $placeholder => $button_token) {
        $body = str_replace($placeholder, $button_token, $body);
      }
      $message['body'] = Markup::create($body);
      $this->sendMessage($webform_submission, $message);
    }
  }

  /**
   * Replaces all button tokens in the given markup text with placeholders.
   *
   * Button tokens follow the pattern btn(title|url), where title is a string
   * displayed as button text and the url defines the location to be called on a click.
   *
   * Both title and url can be tokenized.
   *
   * @param string $markup_text
   *   Reference to a markup text containing button tokens.
   * @param \Drupal\webform\WebformSubmissionInterface $webform_submission
   *   The webform submission
   *
   * @return array
   *   Return the associative array of all button tokens replaced with a placeholder,
   *
   */
  protected function replaceButtonTokens(&$markup_text, $webform_submission) {
    //
    // get occurrences of all buttons in the markup text
    $index = 0;
    $button_tokens = [];
    $last_pos = 0;
    while(($last_pos = strpos($markup_text, 'btn(', $last_pos)) !== false) {
      // find end of button, continue if no end found (incorrect btn definition)
      if (($end = strpos($markup_text, ')', $last_pos)) === false) continue;

      // replace button with placeholder
      $len = $end - $last_pos + 1;
      $button_token = substr($markup_text, $last_pos, $len);
      $placeholder = '%%button_' . $index . '%%';
      $markup_text = str_replace($button_token, $placeholder, $markup_text);
      $button_tokens[$placeholder] = $button_token;
    }

    //
    // convert all button tokens to html, if any and return all converted button tokens
    // in an associative array keyed by their placeholders
    foreach($button_tokens as $key => $button_token) {
      $button_tokens[$key] = $this->convertButtonToken2Html($button_token, $webform_submission);
    }
    return $button_tokens;
  }


  /**
   * Converts a button token to html. Any existing tokens are replaced.
   *
   * @param string $button_token
   *   The button token following the pattern: btn(title|url)
   *
   * @return string
   *   The converted button token as Html string.
   */
  protected function convertButtonToken2Html($button_token, $webform_submission) {
    $markup = '<a class="button" href="%url%">%title%</a>';

    // replace tokens first
    $button_token = $this->replaceTokens($button_token, $webform_submission, [], ['clear' => TRUE]);

    // get title and url
    $button_token = str_replace('btn(', '', $button_token);
    $button_token = str_replace(')', '', $button_token);
    $parameters = explode('|', $button_token);

    // get url only, token returns <a href="url">url</a> or <a href="url">title</a>
    $url_start = strpos($parameters[1], 'href="') + 6;
    $url_len = strpos($parameters[1], '">') - $url_start;
    $parameters[1] = substr($parameters[1], $url_start, $url_len);

    // replace markup tokens and return markup
    $markup = str_replace('%title%', $parameters[0], $markup);
    $markup = str_replace('%url%', $parameters[1], $markup);
    return $markup;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildTokenTreeElement(array $token_types = ['webform', 'webform_submission'], $description = NULL) {
    $description = $description ?: $this->t("Add buttons to body with the following pattern: btn(title|url). 'title' and 'url' can be tokens. </br>Use [webform_submission:values:ELEMENT_KEY:raw] to get plain text values.");
    return parent::buildTokenTreeElement($token_types, $description);
  }

}
