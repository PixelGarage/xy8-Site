<?php


namespace Drupal\stripe_checkout\Event;


use Drupal\stripe_api\Event\StripeApiWebhookEvent;

/**
 * Class StripeChargeSucceededEvent
 * This event is dispatched each time a stripe payment has been successfully completed
 * on the Stripe server (sent also for each recurring payment). It's called via webhook and
 * therefore opens a new browser session.
 *
 * In a checkout process this event is called before the checkout.session.completed event.
 *
 * @package Drupal\stripe_checkout\Event
 */
class StripeChargeSucceededEvent extends StripeApiWebhookEvent {

  /**
   * The Stripe charge.succeeded event.
   */
  const CHARGE_SUCCEEDED = 'stripe_checkout.charge_succeeded';

  /**
   * @var \Stripe\Charge $charge  The charge object.
   */
  public $charge;

  /**
   * User dependant payment parameters.
   *
   * @var array Associative array with additional payment paramters:
   *            - stripe_api_mode:  The test|live mode of Stripe API
   *            - plan_id:          The Stripe subscription plan ID
   *            - stripe_fee:       The calculated stripe fees for this payment.
   *            - app_fee:          The application fee selected by the user.
   */
  public $params;

  /**
   * @var \Stripe\Customer customer The Stripe customer object.
   */
  protected $customer;

  /**
   * StripeChargeSucceededEvent constructor.
   *
   * @param \Drupal\stripe_api\Event\StripeApiWebhookEvent $event
   * @param \Stripe\Charge                                 $charge
   * @param array                                          $params
   */
  public function __construct(StripeApiWebhookEvent $event, \Stripe\Charge $charge, $params) {
    parent::__construct($event->type, $event->data, $event->event);
    $this->charge = $charge;
    $this->customer = null;
    $this->params = $params;
  }

  /**
   * Gets the Stripe customer object.
   * @return mixed
   */
  public function getCustomer() {
    if (!$this->customer && $this->charge) {
      $stripe_cust_id = $this->charge->customer;
      $this->customer = \Stripe\Customer::retrieve($stripe_cust_id);
    }
    return $this->customer;
  }
}
