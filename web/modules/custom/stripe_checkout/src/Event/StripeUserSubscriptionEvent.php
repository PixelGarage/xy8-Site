<?php


namespace Drupal\stripe_checkout\Event;


use Symfony\Component\EventDispatcher\Event;

/**
 * This event is dispatched when a recurring payment subscription has been successfully
 * created or deleted on the Stripe server for a registered Drupal user.
 *
 * @param $user object      The Drupal user object.
 * @param $context  array
 *
 */
class StripeUserSubscriptionEvent extends Event {

  /**
   * Dispatched if a Drupal user has successfully established a Stripe payment subscription.
   */
  const USER_SUBSCRIBED = 'stripe_checkout.user_subscribed';

  /**
   * Dispatched if a Drupal user has successfully deleted a Stripe payment subscription.
   */
  const USER_UNSUBSCRIBED = 'stripe_checkout.user_unsubscribed';

  /**
   * @var object The Drupal user account object
   */
  public $user;

  /**
   * @var array The context information of the Stripe subscription. Only available for
   *            the USER_SUBSCRIBED event, otherwise null
   *
   * The associative array contains the following parameters as key-value pairs:
   *      stripe_api_mode:  The stripe API mode, e.g. test | live.
   *      currency:         The currency of the charged amount.
   *      amount:           The charged amount in currency.
   *      stripe_fee:       The stripe fee in currency.
   *      app_fee:          The application fees in currency.
   *      recurring_billing:The recurring payment interval, e.g. daily|weekly|monthly|yearly
   *      stripe_cust_id:   The Stripe customer id holding the subscription.
 */
  public $context;

  /**
   * StripeUserSubscriptionEvent constructor.
   *
   * @param $user     object    The registered Drupal user object.
   * @param $context  array     An associative array with the given context information
   *                            about the Stripe subscription.
   */
  public function __construct($user, $context = null) {
    $this->user = $user;
    $this->context = $context;
  }
}
