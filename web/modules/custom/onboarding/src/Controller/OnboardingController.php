<?php


namespace Drupal\onboarding\Controller;


use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\onboarding\OnboardingManager;
use Drupal\user\Entity\User;
use Drupal\webform\Entity\Webform;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;


/**
 * Class OnboardingController
 *
 * @package Drupal\onboarding\Controller
 */
class OnboardingController extends ControllerBase {

  /* @var \Drupal\onboarding\OnboardingManager */
  protected $onboarding_manager;

  /**
   * OnboardingController constructor.
   */
  public function __construct(OnboardingManager $onboarding_manager) {
    $this->onboarding_manager = $onboarding_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('onboarding.manager'));
  }

  /**
   * Handles the onboarding process of a user.
   *
   * During the onboarding process the user is still a lead and has no access
   * to its created account (no login possible). The process checks the
   * validity of the user and its given data and in the last step allows the
   * user to set a password for his account. The user is now a validated
   * member.
   *
   * Changing the password means, the created user-specific onboarding url is
   * invalidated and the onboarding process finished.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *    The HTTP request.
   * @param int                                       $uid
   *    The user id.
   * @param int                                       $timestamp
   *    The timestamp
   * @param string                                    $hash
   *    Hash to be validated by user and time.
   *
   * @return array|\Drupal\webform\Entity\Webform|\Symfony\Component\HttpFoundation\RedirectResponse
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public function handleUserOnboardingProcess(Request $request, $uid, $timestamp, $hash) {
    $current = \Drupal::time()->getCurrentTime();
    // check incoming parameters
    if (!$hash || !$timestamp) {
      throw new AccessDeniedHttpException();
    }

    // check if user is valid and inactive
    /** @var \Drupal\user\Entity\User $user */
    $user = User::load($uid);
    if ($user === NULL) {
      // Invalid user ID, so deny access.
      throw new AccessDeniedHttpException();
    }

    // check validity of hash and timestamp
    if ($timestamp >= $current || !$this->onboarding_manager->isValidUserOnboardingHash($user, $timestamp, $hash)) {
      throw new AccessDeniedHttpException();
    }

    //
    // get next onboarding process webform for the user (according to user status)
    $webform_id = $this->onboarding_manager->getNextOnboardingStepForUser($user);
    if (!$webform_id || $webform_id === 'none') {
      // onboarding process not defined correctly (should never happen)
      throw new AccessDeniedHttpException();
    }
    else if ($webform_id === 'finished') {
      //
      // User successfully established a monthly reccurring payment.
      // Let the user's password be changed without the current password
      // check. The password change invalidates the on-boarding URL.
      $token = Crypt::randomBytesBase64(55);
      $_SESSION['pass_reset_' . $user->id()] = $token;
      return $this->redirect(
        'entity.user.edit_form',
        ['user' => $user->id()],
        [
          'query' => ['pass-reset-token' => $token],
          'absolute' => TRUE,
        ]
      );
    }

    //
    // start or continue the onboarding process
    $onboarding_session = &onboarding_session_data();
    $onboarding_session['onboarding_user'] =  $user;

    //
    // return the webform render array
    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = Webform::load($webform_id);
    $webform = $webform->getSubmissionForm();
    return $webform;
  }

  /**
   * Starts the user onboarding process with the onboarding process webform.
   */
  public function startUserOnboardingProcess() {
    //
    // get the onboarding process webform
    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = $this->onboarding_manager->getOnboardingProcessWebform();
    return $webform->getSubmissionForm();
  }

  /**
   * Completes the on-boarding process of the given user.
   *
   * @param \Drupal\user\Entity\User                  $user
   *   The user id.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   */
  public function completeUserOnboardingProcess(User $user) {
    //
    // update and activate user
    $this->onboarding_manager->completeOnboardingUser($user);

    //
    // redirect to destination
    $destination = \Drupal::destination()->get();
    $url = Url::fromUserInput($destination);
    try {
      return $this->redirect($url->getRouteName());
    }
    catch (\Exception $e) {
      //
      // if destination has no corresponding route, redirect to front page
      return $this->redirect('<front>');
    }

  }

}
