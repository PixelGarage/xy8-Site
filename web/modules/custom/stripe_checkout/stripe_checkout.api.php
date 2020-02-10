<?php
/**
 * Contains the API function of the stripe checkout module.
 *
 * Created: ralph
 */

/**
 * This hook allows to define all relations between a stripe checkout button field and a fee button field.
 *
 * Use this hook to relate a stripe checkout button with a fee button, so
 * that the correct fee percentage is used for the clicked stripe button.
 *
 * REMARK: Use machine names of button fields, but replace '_' with '-'.
 * Delta values of fields have not to be considered.
 *
 * @param $stripe_button_relations  array
 *    Empty array to be filled with all stripe button - fee button relations.
 */
function hook_stripe_checkout_fee_button_relation_alter(&$stripe_button_relations) {
  $stripe_button_relations = ['stripe-checkout-button-field-name' => 'fee-button-field-name'];
}

/**
 * This hook alters the feedback associative array to provide a specific feedback for each selectable fee percentage.
 * Use it to give a positive feedback to the user and explain, what the selected fee is used for.
 *
 * REMARK: Keys are the defined fee percentages (string) of a fee button.
 *
 * @param $feedbacks  array
 *    An associative array to be altered with a feedback pro selectable fee percentage.
 * @param $fee_button_id   string
 *    The id of the Stripe fee button field.
 */
function hook_stripe_checkout_fee_button_select_feedback_alter(&$feedbacks, $fee_button_id) {
  if ($fee_button_id == 'field_name_xy') {
    $feedbacks += [
      '0.0' => t('<strong>Too bad!</strong> We are entirely financed by voluntary commission. Your contribution would make a difference.'),
      '0.05' => t('<strong>Thank you!</strong> Your contribution shows us that you appreciate our work.'),
      '0.1' => t('<strong>Wow!</strong> Your contribution allows us to keep this platform up and running.'),
      '0.2' => t('<strong>Amazing!</strong> Your contribution enables us to enhance the functionality of this platform.'),
      '0.3' => t('<strong>Absolutely awesome!</strong> We are very grateful that you honor our work so generously.'),
    ];
  }
}

/**
 * Allows to alter the session parameters, before the checkout session is created.
 *
 * Can be used to store contextual data of the internal system to complete a checkout session.
 *
 * REMARK:
 * - amount, currency and recurring billing cannot be changed (will be reset to original values)
 * - use 'client_reference_id' to store a reference to an internal context object.
 *   This context object can be retrieved again in the checkout.session.completed hook.
 * - store images of the product in $session_params['images']. Must be an array.
 *
 * See also hook_stripe_checkout_session_completed.
 *
 * @param $session_params array
 *    The session parameter array enhanced with some additional data,
 *    e.g. stripe fee and application fee percentage.
 */
function hook_stripe_checkout_session_params_alter(&$session_params) {
  // for example: add images of the item to be payed to the session parameters
}
