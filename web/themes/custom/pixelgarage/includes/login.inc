<?php
/**
 * Adapts the login / register / password forms to a more user-friendly style.
 *
 * User: ralph
 * Date: 06.02.15
 * Time: 16:52
 */

use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Alter the user login form.
 * Adapt the login form so that the user login part displays on the left side
 * and the user registration part on the right.
 *
 * @param $form
 * @param $form_state
 * @param $form_id
 */
function pixelgarage_form_user_login_form_alter(&$form, &$form_state, $form_id) {
  // get routes
  $url_pass = Url::fromRoute('user.pass', [], ['attributes' => ['class' => ['forgot-password', 'link-underline-on'], 'title' => t('Get a new password')]]);
  $url_register = Url::fromRoute('user.register', [], ['attributes' => ['class' => ['button login-register'], 'title' => t('Create new account')]]);
  $pass_link = Link::fromTextAndUrl(t('Forgot your password?'), $url_pass)->toRenderable();
  $register_link = Link::fromTextAndUrl(t('Become a member'), $url_register)->toRenderable();

  // alter form
  $form['name']['#prefix']  = '<div id="' . $form_id . '_form">';
  $form['name']['#prefix'] .= '<h2>' . t('Login') . '</h2>';
  $form['pass']['#suffix']  = '<div class="form-actions-wrapper">';
  $form['pass']['#suffix'] .= \Drupal::service('renderer')->render($pass_link);
  $form['actions']['#suffix']  = '</div></div>';

  // add the registration form on the right side, if applicable
  if (\Drupal::state()->get('user_register', \Drupal\user\UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL) != \Drupal\user\UserInterface::REGISTER_ADMINISTRATORS_ONLY) {
    $account_descr = t("Subscribe here and become part of the Basic Income movement.");
    $registration_descr = t('Once this is confirmed, you can manage your membership in your account at any time.');

    $form['actions']['#suffix'] .= '<div id="user-create-account" class="create-account clearfix">';
    $form['actions']['#suffix'] .= "\n<h2>" . t('I don\'t have an account') . "</h2>";
    $form['actions']['#suffix'] .= "\n<div class='create-account-description'><p>" . $account_descr . "</p>";
    $form['actions']['#suffix'] .= "\n<p>" . t("After successful subscription you will receive a verification email. ") . $registration_descr . "</p></div>";
    $form['actions']['#suffix'] .= "\n<div class='form-actions-wrapper'>" . \Drupal::service('renderer')->render($register_link);
    $form['actions']['#suffix'] .= '</div></div>';
  }
}


/**
 * Alter the user registration form.
 * @param $form
 * @param $form_state
 * @param $form_id
 */
function pixelgarage_form_user_register_form_alter (&$form, &$form_state, $form_id) {
  //
  // add a checkbox with link to participation conditions
  $url_login = Url::fromRoute('user.login', [], ['attributes' => ['class' => ['login-account', 'link-underline-on'], 'title' => t('Sign in')]]);
  $login_link = Link::fromTextAndUrl(t('Back to login'), $url_login)->toRenderable();

  $form['condition_checkbox'] = array(
    '#type' => 'checkbox',
    '#title' => t('I confirm that I have read the <a href="/node/126" class="link-underline-on" target="_blank">Participation Conditions</a>'),
    '#return_value' => 1,
    '#default_value' => 0,
    '#required' => false,
    '#weight' => 3
  );

  //
  // create surrounding div for registration form and back link
  $form['account']['name']['#prefix'] = '<div id="' . $form_id . '">';
  $form['account']['name']['#prefix'] .= '<h2>' . t('Register') . '</h2>';
  $form['actions']['submit']['#suffix'] = '<div class="back-to-login clearfix">' . \Drupal::service('renderer')->render($login_link) . '</div>';
  $form['actions']['submit']['#suffix'] .= '</div>';
}

/**
 * Alter the user password form.
 * @param $form
 * @param $form_state
 * @param $form_id
 */
function pixelgarage_form_user_pass_alter (&$form, &$form_state, $form_id) {
  //
  // create surrounding div with back link
  $url_login = Url::fromRoute('user.login', [], ['attributes' => ['class' => ['login-account', 'link-underline-on'], 'title' => t('Sign in')]]);
  $login_link = Link::fromTextAndUrl(t('Back to login'), $url_login)->toRenderable();

  $form['name']['#prefix'] = '<div id="' . $form_id . '_form">';
  $form['name']['#prefix'] .= '<h2>' . t('Request a new password') . '</h2>';
  $form['actions']['submit']['#suffix'] = '<div class="back-to-login clearfix">' . \Drupal::service('renderer')->render($login_link) . '</div>';
  $form['actions']['submit']['#suffix'] .= '</div>';
}


