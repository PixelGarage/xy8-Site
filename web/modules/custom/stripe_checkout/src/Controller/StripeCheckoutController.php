<?php

namespace Drupal\stripe_checkout\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\stripe_checkout\StripeCheckoutService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Exception;
use Symfony\Component\HttpFoundation\Response;


class StripeCheckoutController extends ControllerBase {

  /**
   * @var \Drupal\stripe_checkout\StripeCheckoutService
   */
  protected $stripeCheckout;

  /**
   * Constructs a StripeCheckoutController object.
   */
  public function __construct(StripeCheckoutService $stripe_checkout) {
    $this->stripeCheckout = $stripe_checkout;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('stripe_checkout.stripe_checkout'));
  }

  /**
   * AJAX callback function to create a stripe checkout session.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function handleSessionCallback(Request $request){
    //
    // get sent button id and retrieve stripe settings of button
    $button_id = $request->request->get('btnID');;
    $stripe_settings = &stripe_checkout_session_data()[$button_id];
    $amount = $stripe_settings['amount'];
    $currency = $stripe_settings['currency'];
    $recurring_billing = $stripe_settings['recurring_billing'];

    //
    // init Stripe
    $this->stripeCheckout->initStripeApi();

    //
    // Create the checkout session
    $resp = ['code' => 200];
    try {
      //
      // allow others to alter session settings (except amount, currency and recurring billing)
      $stripe_settings['images'] = [];
      $this->moduleHandler()->alter('stripe_checkout_session_params', $stripe_settings);
      $stripe_settings['recurring_billing'] = $recurring_billing;
      $stripe_settings['amount'] = $amount;
      $stripe_settings['currency'] = $currency;
      $stripe_settings['stripe_fee'] = $this->stripeCheckout->getStripeFee($amount)/100;
      $stripe_settings['app_fee_percentage'] = $this->stripeCheckout->getAppFeePercentage($button_id);

      //
      // Prepare for a (recurring) payment
      // - anonymous users are identified with the email address
      // - each user can only own one recurring payment meaning there is exactly
      //   one Stripe customer with one subscription attached related to the registered or anonymous user (email).
      // - a new recurring payment for a register user deletes the old Stripe customer and its subscription.
      // - the internal table holds only one entry for each stripe customer (automatic cleaning of old entries)

      // check if a registered user is logged in and return it
      $registered_user = $this->stripeCheckout->getRegisteredUser();
      $user_id = $registered_user ? $registered_user->id() : 0;
      $stripe_settings['customer_email'] = $registered_user ? $registered_user->getEmail() : null;

      //
      // cleanup not processed internal subscriptions (no related Stripe customer id set)
      $this->stripeCheckout->dbCleanupUserSubscriptions($user_id);

      //
      // get/create the subscription plan for the given periodic payment
      $subscription_plan = null;
      if ($recurring_billing !== 'one-time') {
        $subscription_plan = $this->stripeCheckout->getStripePlan($amount, $currency, $recurring_billing);
      }

      //
      // Add a new payment or subscription to the db.
      // A unique event id is stored in stripe_evt_id field, allowing to update
      // this internal subscription in the checkout.session.completed webhook event
      // with the Stripe customer id. This allows to retrieve the app fee for all periodic subscription payments.
      // The unique event id is transferred in the session's client_reference_id field.
      $time = microtime(TRUE);
      $subscr_intl_id = $this->stripeCheckout::INTL_SUBSCR_ID_PREFIX . $time;
      $this->stripeCheckout->dbAddSubscription(
        $user_id,
        $subscription_plan ? $subscription_plan->id : '',
        $subscr_intl_id,
        $stripe_settings['app_fee_percentage']
      );

      //
      // create session
      $combined_reference_id['subscr_intl_id'] = $subscr_intl_id;
      if ($stripe_settings['client_reference_id']) {
        $combined_reference_id['client_reference_id'] = $stripe_settings['client_reference_id'];
      }
      $stripe_settings['client_reference_id'] = json_encode($combined_reference_id);
      $session = $this->stripeCheckout->createStripeCheckoutSession($stripe_settings, $subscription_plan);
      $resp['session_id'] = $session->id;

    }
    catch(\Stripe\Error\Card $e) {
      // The card has been declined
      $resp['message'] = $e->getMessage();
      $resp['code'] = $e->getHttpStatus() ? $e->getHttpStatus() : 400;
    }
    catch(\Stripe\Error\Base $e) {
      $resp['message'] = $e->getMessage();
      $resp['code'] = $e->getHttpStatus() ? $e->getHttpStatus() : 400;
    }
    catch(Exception $e) {
      $resp['message'] = $e->getMessage();
      $resp['code'] = 400;
    }

    // send answer to client
    return new JsonResponse($resp);
  }

  /**
   * AJAX callback of the stripe button custom form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function handleCustomButtonCallback(Request $request) {
    // update the user specified amount
    $button_id = $request->request->get('btnID');
    $new_amount = $request->request->get('newAmount');
    $stripe_settings = &stripe_checkout_session_data()[$button_id];
    $currency = $stripe_settings['currency'];
    $stripe_settings['amount'] = (int)($new_amount*100);

    // check user input
    $input_error =  (empty($new_amount) || !is_numeric($new_amount) || floatval($new_amount) < 1.0);

    // create fixed value stripe button with the new user amount
    if ($input_error) {
      // incorrect user input, return custom button again with error message
      $custom_button = [
        '#theme' => 'stripe_checkout_button_custom',
        '#button_id' => $button_id,
        '#box_title' => t('Incorrect amount'),
        '#box_text' => t('Please correct your input'),
        '#amount' => $new_amount,
        '#currency' => $currency,
        '#stripe_settings' => $stripe_settings,
        '#message' => t('Number must be greater or equal 1.00'),
      ];
    }
    else {
      // correct user input, return button with fixed value
      $custom_button = [
        '#theme' => 'stripe_checkout_button_fix',
        '#button_id' => $button_id,
        '#box_title' => t('Amount to pay'),
        '#box_text' => t('Press button to complete payment'),
        '#amount' => $new_amount,
        '#currency' => $currency,
        '#stripe_settings' => $stripe_settings,
        '#hide-container' => true,
      ];
    }

    // send answer to client (only button html)
    $rendered_button = \Drupal::service('renderer')->render($custom_button);
    return new Response($rendered_button);
  }


  /**
   * AJAX callback on click of fee radios. Used to set user selected fee percentage.
   */
  public function handleFeeCallback(Request $request) {
    // update the selected application fee percentage in session data
    $fee_button_id = $request->request->get('feeButtonID');
    $selected_fee_percentage = floatval($request->request->get('selectedFeePercentage'));
    $session_data = &stripe_checkout_session_data();
    $session_data['fee_buttons'][$fee_button_id] = $selected_fee_percentage;

    // create feedbacks
    $default_feedback = t('<strong>Thank you!</strong> Your contribution shows us that you appreciate our work.');
    $feedbacks = [
      'default' => $default_feedback,
    ];
    $this->moduleHandler()->alter('stripe_checkout_fee_button_select_feedback', $feedbacks, $fee_button_id);

    // get feedback string depending on selected fee button
    $feedback = isset($feedbacks["$selected_fee_percentage"]) ? $feedbacks["$selected_fee_percentage"] : $default_feedback;

    // send answer to client
    return new Response($feedback);
  }


}
