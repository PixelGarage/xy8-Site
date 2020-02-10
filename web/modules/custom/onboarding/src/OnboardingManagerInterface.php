<?php


namespace Drupal\onboarding;


interface OnboardingManagerInterface {
  /**
   * Defines the webform category for the on-boarding process webform.
   */
  const ONBOARDING_PROCESS_WEBFORM_CATEGORY = 'Onboarding process';

  /**
   * Defines the webform category for the on-boarding process webform.
   */
  const ONBOARDING_STEP_WEBFORM_CATEGORY = 'Onboarding step';

  /**
   * Defines the webform handler id defining the on-boarding process.
   */
  const ONBOARDING_PROCESS_WEBFORM_HANDLER_ID = 'onboarding_process';

  /**
   * The user state at the beginning of the on-boarding process.
   */
  const ONBOARDING_INIT = 0;

  /**
   * The user on-boarding state after the IBAN has been successfully confirmed.
   */
  const IBAN_CHECKED = 1;

  /**
   * The user on-boarding state after the recurring payment has been successfully defined.
   */
  const RECURRING_PAYMENT_DEFINED = 2;

  /**
   * The user on-boarding state during the setup period of a recurring payment.
   */
  const RECURRING_PAYMENT_ESTABLISHING = 3;

  /**
   * The user state at the end of the on-boarding process.
   */
  const ONBOARDING_COMPLETED = 100;


  /*****************************************************************************
   * User methods
   ****************************************************************************/
  /**
   * Creates or updates a user account with the given parameter.
   *
   * @param $name       string  The user name.
   * @param $email      string  The user email.
   * @param $amount     float   The transferred amount on the IBAN account.
   * @param $lang_code  string  Language code for user account.
   *
   * @return bool|\Drupal\user\Entity\User
   */
  public function createAccount($name, $email, $amount, $lang_code);

  /**
   * Updates the user with the collected values and on-boarding status.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user account.
   *
   * @param  array $values
   *   The entered values of the on-boarding step.
   *
   * @return mixed
   */
  public function updateOnboardingUser($user, $values);

  /**
   * Complete the on-boarding process for the given user.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user account
   */
  public function completeOnboardingUser($user);

  /**
   * Creates a unique url for the given user to perform the onboarding process.
   *
   * @param \Drupal\user\UserInterface $user
   *    An object containing the user account.
   * @param  int $timestamp
   *    The timestamp of creation.
   * @param array                      $options
   *    (optional) A keyed array of settings. Supported options are:
   *    - absolute: If option is set and TRUE, an absolute URL is created. Otherwise a relative url is returned.
   *    - langcode: A language code to be used when generating locale-sensitive
   *    URLs. If langcode is NULL the users preferred language is used.
   *
   * @return \Drupal\Core\Url
   */
  public function createUserOnboardingUrl($user, $timestamp, $options = []);

  /**
   * Checks, if the onboarding hash is valid for the given user id and timestamp.
   *
   * @param \Drupal\user\UserInterface $user
   *     The user id.
   * @param int $timestamp
   *     The timestamp from the url.
   * @param string $hash
   *     The hash from the url
   *
   * @return bool
   *    True, if hash is valid, false otherwise
   */
  public function isValidUserOnboardingHash($user, $timestamp, $hash);


  /*****************************************************************************
   * Onboarding process methods
   ****************************************************************************/
  /**
   * Gets the webform, that initiates the on-boarding process.
   *
   * @return mixed
   */
  public function getOnboardingProcessWebform();

  /**
   * Returns the on-boarding step definition as an associated array with the user on-boarding
   * status as key and a status title as value.
   *
   * @return array
   *   Returns the associative array of all on-boarding steps.
   */
  public function getOnboardingStepDefinitions();

  /**
   * Returns the next on-boarding step webform for the user.
   *
   * @param \Drupal\user\Entity\User $user
   *  The user account
   *
   * @return mixed
   *  Returns the webform id of the next step according to the user status.
   *  'finished' is returned, if the user is successfully on-boarded but has not changed his password.
   *  False, if no next step could be evaluated.
   */
  public function getNextOnboardingStepForUser($user);

  /**
   * Evaluates if the form with the given form_id belongs to the on-boarding process.
   *
   * @param string $form_id
   *   The form id.
   *
   * @return bool
   *   Returns true, if form with given form_id is an on-boarding step webform.
   */
  public function isOnboardingStepWebform($form_id);

}
