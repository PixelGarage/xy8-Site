<?php
/**
 * Created by PhpStorm.
 * User: ralph
 * Date: 06.02.15
 * Time: 17:22
 */


/* =============================================================================
 *    Stripe button alter hooks implementation
 * ========================================================================== */
/**
 * This hook allows to define all relations between a stripe button field and a fee button field.
 *
 * Use this hook to relate a stripe button field with a fee button field, so
 * that the correct fee percentage is used for the pressed stripe button.
 *
 * @param $stripe_button_relations  array
 *    Empty array to be filled with all stripe button - fee button relations.
 */
function pixelgarage_stripe_button_fee_button_relation_alter(&$stripe_button_relations) {
  $stripe_button_relations = array(
    'field-spenden' => 'field-spenden-fee-percentage',
    'field-spenden-predefined' => 'field-spenden-fee-percentage',
    'field-recurr-spenden' => 'field-recurr-spenden-fee-percent',
    'field-recurr-spenden-predefined' => 'field-recurr-spenden-fee-percent',
  );
}

/**
 * This hook alters the feedback associative array to provide a feedback for each selectable fee percentage button.
 * Use it to give a positive feedback to the user and explain, what the selected fee is used for.
 */
function pixelgarage_stripe_button_fee_select_feedback_alter(&$feedbacks, $fee_button_id) {
  if ($fee_button_id == 'field-spenden-fee-percentage') {
    $feedbacks += array (
      '0' => t('<strong>Too bad!</strong> We are entirely financed by voluntary commission. Your contribution would make a difference.'),
      '0.05' => t('<strong>Thank you!</strong> Your contribution shows us that you appreciate our work.'),
      '0.1' => t('<strong>Wow!</strong> Your contribution allows us to keep this platform up and running.'),
      '0.2' => t('<strong>Amazing!</strong> Your contribution enables us to enhance the functionality of this platform.'),
      '0.3' => t('<strong>Absolutely awesome!</strong> We are very grateful that you honor our work so generously.'),
    );
  }
  else if ($fee_button_id == 'field-recurr-spenden-fee-percent') {
    $feedbacks += array (
      '0.1' => t('<strong>Thank you!</strong> Your contribution shows us that you appreciate our work.'),
      '0.2' => t('<strong>Thank you!</strong> Your contribution shows us that you appreciate our work.'),
      '0.3' => t('<strong>Wow!</strong> Your contribution helps us to finance the administration tasks.'),
      '0.4' => t('<strong>Amazing!</strong> Your contribution enables us to enhance the functionality of this platform.'),
      '0.5' => t('<strong>Absolutely awesome!</strong> We are very grateful that you honor our work so generously.'),
    );
  }
}


/* =============================================================================
 *    Follow alter hooks implementation
 * ========================================================================== */
/**
 * Alter the available networks to the Follow module.
 *
 * @param $networks
 *   Associative array of networks that are available.
 * @param $uid
 *   The User ID of the networks to be displayed. If 0 is provided, will be the
 *   networks for the website rather then an individual user.
 */
function pixelgarage_follow_networks_alter(&$networks, $uid = 0) {
  // Add a network.
  unset($networks[$uid]['googleplus']);
  $networks[$uid]['instagram'] = array(
    'title' => t('Instagram'),
    'domain' => 'instagram.com',
  );
}

