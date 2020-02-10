<?php


namespace Drupal\onboarding;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Drupal\webform\Entity\Webform;
use Exception;
use Psr\Log\LoggerInterface;


class OnboardingManager implements OnboardingManagerInterface {

  use StringTranslationTrait;

  /**
   * Holds the onboarding manager logger.
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Holds the onboarding steps as key => value pairs.
   * @var array
   */
  protected $onboarding_steps = null;

  /**
   * Constructs a StripeCheckoutService object.
   */
  public function __construct() {
    $this->logger = \Drupal::logger('onboarding_manager');
  }


  /*****************************************************************************
   * User methods
   ****************************************************************************/

  /**
   * {@inheritdoc}
   */
  public function createAccount($name, $email, $amount, $lang_code) {
    $initStatus = $this->amountToStatus($amount);

    /* @var \Drupal\user\Entity\User $user */
    if ($user = user_load_by_mail($email)) {
      $user->set("langcode", $lang_code);
      $user->set("preferred_langcode", $lang_code);
      $user->set("preferred_admin_langcode", $lang_code);
      if ($user->get("field_onboarding_status")->isEmpty()) {
        $user->set("field_onboarding_status", $initStatus);
      }
    }
    else {
      $user = User::create();
      $pwd = user_password(12); // create random password with 12 letters

      // basic user settings
      $user->setUsername($email);
      $user->setPassword($pwd);
      $user->setEmail($email);
      $user->enforceIsNew();

      // optional settings
      $user->field_address->given_name = $name;
      $user->set("init", $email); // initial email used for creation
      $user->set("langcode", $lang_code);
      $user->set("preferred_langcode", $lang_code);
      $user->set("preferred_admin_langcode", $lang_code);
      $user->set("field_onboarding_status", $initStatus);
    }

    // always update user on-boarding link and save user account
    try {
      $result = $user->save();
    }
    catch (Exception $e) {
      $result = false;
      $this->logger->error('The following error occurred: @error', ['@error' => $e->getMessage()]);
    }

    return $result ? $user : false;
  }

  /**
   * {@inheritdoc}
   */
  public function updateOnboardingUser($user, $values) {
    try {
      $status = $this->getUserStatus($user);
      switch ($status) {
        case static::ONBOARDING_INIT:
          $new_status = static::IBAN_CHECKED;
          break;
        case static::IBAN_CHECKED:
          $user->set("field_monthly_amount", $values['monthly_amount']);
          $user->set("field_payment_method", $values['payment_method']);
          $new_status = static::RECURRING_PAYMENT_DEFINED;
          break;
        case static::RECURRING_PAYMENT_DEFINED:
          $new_status = static::RECURRING_PAYMENT_ESTABLISHING;
          break;
        default:
          return;
      }
      $user->set("field_onboarding_status", $new_status);
      $user->save();
    }
    catch (Exception $e) {
      $this->logger->error('The user could not be updated: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function completeOnboardingUser($user) {
    //
    // only complete if user is in status RECURRING_PAYMENT_ESTABLISHING
    $status = $this->getUserStatus($user);
    if ($status !== static::RECURRING_PAYMENT_ESTABLISHING) return;

    try {
      $user->set('field_onboarding_link', null);
      $user->set('field_onboarding_status', static::ONBOARDING_COMPLETED);
      $user->activate();
      $user->save();
    }
    catch (Exception $e) {
      $this->logger->error('The user could not be updated: @error', ['@error' => $e->getMessage()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createUserOnboardingUrl($user, $timestamp, $options = []) {
    $lang_code = isset($options['langcode']) ? $options['langcode'] : $user->getPreferredLangcode();
    $absolute_url = isset($options['absolute']) && $options['absolute'];
    $url_options = [
      'absolute' => $absolute_url,
      'language' => \Drupal::languageManager()->getLanguage($lang_code),
    ];
    return Url::fromRoute('onboarding.user', [
      'uid' => $user->id(),
      'timestamp' => $timestamp,
      'hash' => $this->timedUserHash($user, $timestamp),
    ], $url_options);
  }

  /**
   * {@inheritdoc}
   */
  public function isValidUserOnboardingHash($user, $timestamp, $hash) {
    return Crypt::hashEquals($hash, $this->timedUserHash($user, $timestamp));
  }

  /**
   * Creates a unique hash value used during a user specific onboarding process.
   *
   * The hash is created with the timestamp, the user id and email.
   * In order to validate the URL, the hash can be generated again with these values
   * and compared to the hash value from the URL.
   *
   * A password or email change of the user invalidates a generated hash.
   *
   * @param \Drupal\user\UserInterface $user
   * @param int $timestamp
   *
   * @return string
   *    The unique HMAC Base64 hash for the user.
   */
  protected function timedUserHash($user, $timestamp) {
    $data = $timestamp;
    $data .= $user->id();
    $data .= $user->getEmail();
    return Crypt::hmacBase64($data, Settings::getHashSalt() . $user->getPassword());
  }

  /**
   * Gets the on-boarding status of the user.
   *
   * @param \Drupal\user\Entity\User $user
   *
   * @return int|mixed
   *   The on-boarding status of the user.
   */
  protected function getUserStatus($user) {
    $status = $user->get('field_onboarding_status')->isEmpty() ? static::ONBOARDING_INIT :
      max(static::ONBOARDING_INIT, $user->field_onboarding_status->value);
    return intval($status);
  }



  /*****************************************************************************
   * Onboarding process methods
   ****************************************************************************/
  /**
   * {@inheritdoc}
   */
  public function getOnboardingProcessWebform() {
    try {
      // get onboarding webform
      $webform_ids = \Drupal::entityQuery('webform')
        ->condition('category', static::ONBOARDING_PROCESS_WEBFORM_CATEGORY)
        ->execute();

      foreach($webform_ids as $webform_id) {
        return Webform::load($webform_id);
      }
    }
    catch (\Exception $e) {
      // no handler found
      return null;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOnboardingStepDefinitions() {
    // define onboarding steps
    $onboarding_steps = [
      static::ONBOARDING_INIT => $this->t('First step: Check bank account (IBAN)'),
      static::IBAN_CHECKED => $this->t('Second step: Define recurring payment'),
      static::RECURRING_PAYMENT_DEFINED . '_ebanking' => $this->t('Third step: E-Banking payment'),
      static::RECURRING_PAYMENT_DEFINED . '_card' => $this->t('Third step: Card payment'),
      static::RECURRING_PAYMENT_DEFINED . '_lsv' => $this->t('Third step: LSV+ payment'),
      static::RECURRING_PAYMENT_DEFINED . '_invoice' => $this->t('Third step: Invoice payment'),
    ];

    return $onboarding_steps;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextOnboardingStepForUser($user) {
    //
    // user on-boarding process finished
    $status = $this->getUserStatus($user);
    if ($status === static::ONBOARDING_COMPLETED) {
      return 'finished';
    }

    // if payment method is defined, combine status with payment method as step key
    $payment_method_field = $user->get('field_payment_method');
    $payment_method = $payment_method_field->isEmpty() ? '' : $user->field_payment_method->value;
    if ($status == static::RECURRING_PAYMENT_DEFINED && $payment_method) {
      $status .= '_' . $payment_method;
    }

    // get on-boarding steps
    $steps = $this->getOnboardingStepWebforms();
    return isset($steps[$status]) ? $steps[$status] : false;
  }

  /**
   * {@inheritdoc}
   */
  public function isOnboardingStepWebform($form_id) {
    //
    // no webform submission form
    if (strpos($form_id, 'webform_submission_') === FALSE ||
        strpos($form_id, '_add_form') === FALSE) return false;

    $form_id = str_replace('webform_submission_', '', $form_id);
    $form_id = str_replace('_add_form', '', $form_id);
    $steps = $this->getOnboardingStepWebforms();
    if (!is_array($steps)) return false;
    $form_ids = array_values($steps);
    return in_array($form_id, $form_ids);
  }

  /**
   * Returns the onboarding steps as an array of step_key => webform_id entries.
   *
   * @return array
   */
  protected function getOnboardingStepWebforms() {
    //
    // get onboarding process webform
    if (!$this->onboarding_steps) {
      try {
        // get onboarding process webform
        $webform = $this->getOnboardingProcessWebform();
        $onboarding_process_handler = $webform->getHandler(static::ONBOARDING_PROCESS_WEBFORM_HANDLER_ID);
        $this->onboarding_steps = $onboarding_process_handler ?
          $onboarding_process_handler->getConfiguration()['settings']['steps'] : null;
      }
      catch (\Exception $e) {
        // no handler found
        return null;
      }
    }

    return $this->onboarding_steps;
  }



  /*****************************************************************************
   * Helper methods
   ****************************************************************************/

  /**
   * Helper function to convert given amount to on-boarding status.
   *
   * @param float $amount
   *
   * @return int
   *   The converted amount as onboarding status.
   */
  protected function amountToStatus($amount) {
    return -intval($amount*100);
  }

  /**
   * Converts a status (negative value) back to the amount (float)
   *
   * @param int $status
   *
   * @return float|int
   */
  protected function statusToAmount($status) {
    return (-$status)/100;
  }

}
