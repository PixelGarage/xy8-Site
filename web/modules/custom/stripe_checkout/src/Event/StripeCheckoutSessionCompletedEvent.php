<?php


namespace Drupal\stripe_checkout\Event;


use Drupal\stripe_api\Event\StripeApiWebhookEvent;

/**
 * This event is dispatched via webhook when the checkout session has been completed
 * on the Stripe server. Use it to complete the checkout payment in the local system.
 *
 * REMARK: A webook call opens a new browser session, so no session settings are
 * available anymore. To retrieve contextual data of the internal system to complete
 * the checkout session, you can use the context associative array.
 * (See $context for details of the available payment parameters)
 *
 * See also hook_stripe_checkout_session_params_alter.
 *
 * @param $session \Stripe\Checkout\Session
 *    The Stripe session object
 */
class StripeCheckoutSessionCompletedEvent extends StripeApiWebhookEvent {
  /**
   * The Stripe checkout.session.completed event.
   */
  const CHECKOUT_SESSION_COMPLETED = 'stripe_checkout.checkout.session.completed';

  /**
   * @var \Stripe\Checkout\Session $session  The checkout session object.
   */
  public $session;

  /**
   * The session context. Makes user and session dependant payment parameters available.
   *
   * @var array Associative array with additional payment paramters:
   *            - stripe_api_mode:  The test|live mode of Stripe API
   *            - recurring_billing:The recurring billing mode, 'one-time' | 'daily' | 'weekly' | 'monthly' | 'yearly'
   *            - plan_id:          The Stripe subscription plan ID
   *            - stripe_fee:       The calculated stripe fees for this payment.
   *            - app_fee:          The application fee selected by the user.
   *            - client_reference_id: The session client context (client_reference_id)
   */
  public $context;

  /**
   * @var \Stripe\Customer customer The Stripe customer object.
   */
  protected $customer;

  /**
   * @var \Stripe\PaymentIntent $paymentIntent The Stripe payment intent in case of a single payment.
   */
  protected $paymentIntent;

  /**
   * @var \Stripe\Subscription $subscription The Stripe subscription in case of a recurring payment.
   */
  protected $subscription;


  /**
   * StripeChargeSucceededEvent constructor.
   *
   * @param \Drupal\stripe_api\Event\StripeApiWebhookEvent $event
   * @param \Stripe\Checkout\Session                       $session
   * @param array                                          $context
   */
  public function __construct(StripeApiWebhookEvent $event, \Stripe\Checkout\Session $session, $context) {
    parent::__construct($event->type, $event->data, $event->event);
    $this->session = $session;
    $context['client_reference_id'] = $session->client_reference_id;
    $this->context = $context;
    $this->customer = is_object($session->customer) ? $session->customer : null;
    $this->paymentIntent = is_object($session->payment_intent) ? $session->payment_intent : null;
    $this->subscription = null;
  }

  /**
   * Gets the Stripe customer object.
   * @return mixed
   */
  public function getCustomer() {
    if (!$this->customer && $this->session) {
      $stripe_cust_id = $this->session->customer;
      $this->customer = \Stripe\Customer::retrieve($stripe_cust_id);
    }
    return $this->customer;
  }

  /**
   * Gets the Stripe payment intent in case of a single payment, null otherwise.
   * @return \Stripe\PaymentIntent
   */
  public function getPaymentIntent() {
    if (!$this->paymentIntent && $this->session) {
      $pay_id = $this->session->paymentIntent;
      $this->paymentIntent = \Stripe\PaymentIntent::retrieve($pay_id);
    }
    return $this->paymentIntent;
  }

  /**
   * Gets the Stripe subscription in case of a recurring payment, null otherwise.
   * @return \Stripe\Subscription
   */
  public function getSubscription() {
    if (!$this->subscription && $this->session) {
      $subscr_id = $this->session->subscription;
      $this->subscription = \Stripe\Subscription::retrieve($subscr_id);
    }
    return $this->subscription;
  }

}
