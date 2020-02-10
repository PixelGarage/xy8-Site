<?php
/**
 * Preprocess variables of a Stripe Checkout button with a predefined amount in a given currency.
 */
function template_preprocess_stripe_checkout_button_fix(&$variables) {
  //
  // store button settings in session
  $button_id = $variables['button_id'];
  $stripe_settings = _prepare_stripe_checkout_settings($variables['stripe_settings'], $variables['amount']);
  $session_data = &stripe_checkout_session_data();
  $session_data[$button_id] = $stripe_settings;

  //
  // cleanup variables
  _cleanup_button_variables($variables, $stripe_settings);

  // create HTML button
  if ($variables['amount'] > 0) {
    //
    // Create initial button
    // Enforce strict content security policy
    if ($variables['csp']) {
      header("Content-Security-Policy: default-src 'self' " . STRIPE_CHECKOUT_JAVASCRIPT_PATH . ";");
    }

    $variables['button_label'] = t('@amount @currency', array('@amount' => $variables['amount'], '@currency' => $variables['currency']));
  }
  else if ($variables['amount'] == 0) {
    $variables['button_label'] = t("@submit successful", ['@submit' => $stripe_settings['submit_text']]);
  }
  else {
    $variables['button_label'] = t("@submit failed", ['@submit' => $stripe_settings['submit_text']]);
  }

  //
  // add js settings
  $checkout_buttons = [$button_id => $button_id];
  _add_stripe_checkout_js($variables, $checkout_buttons, []);
}
