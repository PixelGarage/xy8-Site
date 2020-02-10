<?php

/**
 * Preprocess variables of a Stripe Checkout button with a customizable amount in a given currency.
 */
function template_preprocess_stripe_checkout_button_custom(&$variables) {
  //
  // store button settings in session
  $button_id = $variables['button_id'];
  $stripe_settings = _prepare_stripe_checkout_settings($variables['stripe_settings'], $variables['amount']);
  $session_data = &stripe_checkout_session_data();
  $session_data[$button_id] = $stripe_settings;

  //
  // cleanup variables
  _cleanup_button_variables($variables, $stripe_settings);

  //
  // prepare custom form
  $session_data['button_id'] = $button_id;
  $formArray = \Drupal::formBuilder()->getForm('\Drupal\stripe_checkout\Form\CustomButtonForm');
  $variables['button_form'] = \Drupal::service('renderer')->render($formArray);
  //
  // add js settings
  $custom_buttons = [$button_id => $button_id];
  _add_stripe_checkout_js($variables, $custom_buttons, $custom_buttons);
}

