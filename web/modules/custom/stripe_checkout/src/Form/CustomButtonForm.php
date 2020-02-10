<?php


namespace Drupal\stripe_checkout\Form;


use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class CustomButtonForm extends FormBase {

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
    return 'stripe_checkout_custom_button_form';
  }

  /**
   * Form constructor.
   *
   * @param array                                $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // get form values
    $session_data = &stripe_checkout_session_data();
    $button_id = $session_data['button_id'];
    unset($session_data['button_id']);
    $stripe_settings = $session_data[$button_id];
    $amount = $stripe_settings['amount'] / 100;
    $currency = $stripe_settings['currency'];
    $submit_action = $stripe_settings['submit_action'];

    // add wrapper to entire form
    $form['#prefix'] = '<div id="form-' . $button_id . '" class="stripe-button-custom-form">';
    $form['#suffix'] = '</div>';
    $form['stripe_checkout_custom_amount'] = array(
      '#type' => 'textfield',
      '#default_value' => $amount,
      '#title' => t('Amount'),
      '#title_display' => 'invisible',
      '#size' => 7,
      '#weight' => 0,
      '#prefix' => '<div class="input-group"><div class="input-group-addon">'. $currency . '</div>',
      '#suffix' => '</div>',
    );
    $form['stripe_checkout_custom_submit'] = array(
      '#type' => 'button',
      '#value' => $submit_action,
      '#weight' => 1,
    );

    return $form;
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
    // is submitted by javaScript (Ajax-call)
  }

}
