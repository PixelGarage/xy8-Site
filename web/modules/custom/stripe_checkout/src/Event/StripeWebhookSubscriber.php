<?php

namespace Drupal\stripe_checkout\Event;

use Drupal\Component\Utility\Crypt;
use Drupal\user\Entity\User;
use Exception;
use Drupal\stripe_api\Event\StripeApiWebhookEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class StripeWebhookSubscriber.
 *
 * Provides the webhook subscriber functionality.
 */
class StripeWebhookSubscriber implements EventSubscriberInterface {

  /**
   * @var \Drupal\stripe_checkout\StripeCheckoutService
   */
  protected $stripeCheckout;

  /**
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  static public function getSubscribedEvents() {
    return [
      StripeApiWebhookEvent::WEBHOOK => 'onIncomingEvent',
    ];
  }

  /**
   * Process an incoming webhook event.
   * Dispatches all relevant Stripe events separately for the Stripe checkout process.
   *
   * @param \Drupal\stripe_api\Event\StripeApiWebhookEvent $event
   */
  public function onIncomingEvent(StripeApiWebhookEvent $event) {
    //
    // dispatch charge.succeeded and checkout.session.completed events
    $this->stripeCheckout = \Drupal::service('stripe_checkout.stripe_checkout');
    $this->eventDispatcher = \Drupal::service('event_dispatcher');


    if ($event->type === 'charge.succeeded') {
      //
      // Check charge.succeeded event object.
      $charge = $event->data->object;
      if ($charge->object !== 'charge') {
        $this->stripeCheckout->getLogger()->error('Webhook charge.succeeded event: Incomplete or wrong charge object: @data', [
          '@data' => json_encode($event->data),
        ]);
        return;
      }
      //
      // Process charge.succeeded event for all successful payments.
      $this->chargeSucceeded($event);
    }
    else if ($event->type === 'checkout.session.completed') {
      //
      // Check checkout.session.completed event object.
      $session = $event->data->object;
      if ($session->object !== 'checkout.session') {
        $this->stripeCheckout->getLogger()->error('Webhook checkout.session.completed event: Missing session object: @data', [
          '@data' => json_encode($event->data),
        ]);
      }
      //
      // Process checkout.session.completed event initialized on Stripe server.
      $this->checkoutSessionCompleted($event);
    }

  }

  /**
   * Stripe event charge.succeeded webhook implementation.
   *
   * The implementation guarantees the following:
   * - each event is only processed once (idempotency).
   * - the event is only processed, if a customer is attached to the event and a subscription for
   *    this customer exists with a different event id stored.
   * - if no subscription or a subscription with no event id is found,
   *    the event is sent due to a user
   *
   * @param \Drupal\stripe_api\Event\StripeApiWebhookEvent $event
   */
  protected function chargeSucceeded(StripeApiWebhookEvent $event) {
    $time = date('d-m-Y H:i:s');
    $this->stripeCheckout->getLogger()->debug('Webhook charge.succeeded event received: ' . $time);

    //
    // get charged customer
    $app_fee_percentage = 0;
    $plan_id = null;
    $charge = $event->data->object;
    $stripe_cust_id = $charge->customer;

    //
    // update internal subscription, if any
    if ($subscription = $this->stripeCheckout->dbGetSubscription($stripe_cust_id)) {
      //
      // check if incoming event has been processed already (idempotency)
      $stripe_evt_id = $event ? $event->event->id : 'evt_' . Crypt::hashBase64($time);  // create test event id in TEST mode, always perform event
      $evt_id = $subscription['stripe_evt_id'];
      if ($evt_id === $stripe_evt_id) {
        $message = 'Incoming event already processed.';
        $this->stripeCheckout->getLogger()->debug( 'Webhook charge.succeeded event: @msg', array('@msg' => $message));
        return;
      }
      //
      // update the subscription with the new Stripe customer id and the event id
      $this->stripeCheckout->dbUpdateSubscription($stripe_cust_id, $stripe_evt_id);

      //
      // calculate app_fee from subscription
      $plan_id = $subscription['stripe_plan_id'];
      $app_fee_percentage = $subscription['app_fee_percentage'];
    }

    //
    // Inform all charge.succeeded subscribers about charge
    // get charge parameters
    $amount = $charge->amount;
    $params['stripe_api_mode'] = strtolower($this->stripeCheckout->getStripeApi()->getMode());
    $params['plan_id'] = $plan_id;
    $params['stripe_fee'] = $this->stripeCheckout->getStripeFee($amount)/100;
    $params['app_fee'] = $amount * $app_fee_percentage/100;
    $chargeSucceededEvent = new StripeChargeSucceededEvent($event, $charge, $params);
    $this->eventDispatcher->dispatch(StripeChargeSucceededEvent::CHARGE_SUCCEEDED, $chargeSucceededEvent);

  }

  /**
   * Implements the checkout.session.completed Stripe event.
   *
   * @param \Drupal\stripe_api\Event\StripeApiWebhookEvent $event
   */
  protected function checkoutSessionCompleted(StripeApiWebhookEvent $event) {
    $time = date('d-m-Y H:i:s');
    $this->stripeCheckout->getLogger()->debug('Webhook checkout.session.completed event received: ' . $time, array());

    //
    // expand customer, payment and client_reference_id in session
    $event_dispatcher = \Drupal::service('event_dispatcher');
    $subscr_intl_id = false;
    $context = [];
    $session = $event->data->object;
    try {
      $pay_id = $session->payment_intent;
      $billing_details = null;
      if ($pay_id) {
        $payment = \Stripe\PaymentIntent::retrieve($pay_id);
        $charge = $payment->charges->data[0];
        $billing_details = $charge ? $charge->billing_details : null;
        $session->payment_intent = $payment;
      }
      $stripe_cust_id = $session->customer;
      if ($stripe_cust_id) {
        $customer = \Stripe\Customer::retrieve($stripe_cust_id);
        $customer_mail = $customer->email;
        $customer->metadata = $billing_details;
        $session->customer = $customer;
      }

      // unpack client reference id
      if ($session->client_reference_id) {
        $combined_reference_id =  json_decode($session->client_reference_id);
        $session->client_reference_id = isset($combined_reference_id->client_reference_id) ?
          $combined_reference_id->client_reference_id : null;
        $subscr_intl_id = $combined_reference_id->subscr_intl_id ? $combined_reference_id->subscr_intl_id : false;
      }
    }
    catch (Exception $e) {
      // do nothing
      $customer_mail = '';
    }


    if ($subscr_intl_id && $subscription = $this->stripeCheckout->dbGetSubscription($stripe_cust_id, $subscr_intl_id)) {
      //
      // set single payment internal parameters
      if ($pay_id) {
        $amount = $payment->amount;
        $context = [
          'stripe_api_mode' => strtolower($this->stripeCheckout->getStripeApi()->getMode()),
          'recurring_billing' => 'one-time',
          'plan_id' => null,
          'stripe_fee' => $this->stripeCheckout->getStripeFee($amount)/100,
          'app_fee' => $amount * $subscription['app_fee_percentage']/100
        ];
      }
      else {
        //
        // Subscriptions:
        // update the internal subscription with the customer id and the new Stripe event id
        $stripe_evt_id = $event ? $event->event->id : 'evt_' . Crypt::hashBase64($time);  // create test event id in TEST mode, always perform event
        $this->stripeCheckout->dbUpdateSubscription($stripe_cust_id, $stripe_evt_id, $subscr_intl_id);

        //
        // delete all Stripe customers with the same email as the anonymous/registered user
        // and its subscriptions
        $this->stripeCheckout->deleteCustomerByEmail($customer_mail, $stripe_cust_id);

        //
        // successful new Stripe subscription created for registered or anonymous user,
        $context = $this->stripeCheckout->getParamsFromSubscription($subscription);
        $registered_user = $subscription['user_id'] ? User::load($subscription['user_id']) : false;
        if ($registered_user) {
          // Registered user
          // inform other modules about new Stripe subscription of user
          $userSubscriptionEvent = new StripeUserSubscriptionEvent($registered_user, $context);
          $this->eventDispatcher->dispatch(StripeUserSubscriptionEvent::USER_SUBSCRIBED, $userSubscriptionEvent);
        }
      }

    }

    // inform all subscribers about the completed checkout session
    $sessionCompletedEvent = new StripeCheckoutSessionCompletedEvent($event, $session, $context);
    $this->eventDispatcher->dispatch(StripeCheckoutSessionCompletedEvent::CHECKOUT_SESSION_COMPLETED, $sessionCompletedEvent);
  }

}
