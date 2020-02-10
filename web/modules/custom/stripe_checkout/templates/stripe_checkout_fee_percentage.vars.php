<?php
/**
 * The preprocess function for the stripe button fee block.
 */

function template_preprocess_stripe_checkout_fee_percentage(&$vars) {
  //
  // define stripe button fee block
  $field_id = $vars['field_id'];
  $default_fee_percentage = $vars['fee_items'][$vars['default_button_index']];
  $session_data = &stripe_checkout_session_data();
  $session_data['fee_buttons'][$field_id] = $default_fee_percentage;

  $vars['top_text'] = !empty($vars['top_text']) ? t($vars['top_text']) : t('We are financed entirely by voluntary contributions. Please support our work with');
  $vars['bottom_text'] = !empty($vars['bottom_text']) ? t($vars['bottom_text']) : t('of your payment.');
  $vars['stripe_fee_text'] = !empty($vars['stripe_fee_text']) ? t($vars['stripe_fee_text']) : t('2.9% + 0.30 CHF transaction fees will always be deducted from your payment.');
  $vars['answer_text'] = '';

  //
  // add js library
  $variables['#attached']['library'][] = 'stripe_checkout/stripe-fee';
}
