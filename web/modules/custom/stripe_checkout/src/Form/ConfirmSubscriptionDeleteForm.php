<?php

namespace Drupal\stripe_checkout\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;

class ConfirmSubscriptionDeleteForm extends ConfirmFormBase {

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * Returns a unique string identifying the form.
   *
   * The returned ID should be a unique string that can be a valid PHP function
   * name, since it's used in hook implementation names such as
   * hook_form_FORM_ID_alter().
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'stripe_checkout_confirm_subscription_delete';
  }

  /**
   * Returns the question to ask the user.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The form question. The page title will be set to this value.
   */
  public function getQuestion() {
    return t('Are you sure you want to delete all subscriptions for user @name?', ['@name' => $this->user->getDisplayName()]);
  }

  /**
   * Returns the route to go to if the user cancels the action.
   *
   * @return \Drupal\Core\Url
   *   A URL object.
   */
  public function getCancelUrl() {
    return Url::fromUri('entity:user/' . $this->user->id() . '/edit');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, AccountInterface $user = NULL) {
    $this->user = $user;
    return parent::buildForm($form, $form_state);
  }

  /**
   * Form submission handler.
   *
   * @param array                                $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // delete subscription
    $stripe_checkout = \Drupal::service('stripe_checkout.stripe_checkout');
    $stripe_checkout->initStripe();
    $stripe_checkout->deleteUserSubscriptions($this->user);

    // redirect to pixel structure main config page
    $form_state['redirect'] = "user/{$this->user->id()}/edit";
  }
}
