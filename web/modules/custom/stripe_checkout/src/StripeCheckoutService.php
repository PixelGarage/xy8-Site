<?php

namespace Drupal\stripe_checkout;

use Drupal\Core\Database\Connection;
use Drupal\stripe_api\StripeApiService;
use Drupal\stripe_checkout\Event\StripeUserSubscriptionEvent;
use Exception;

/**
 * Class StripeCheckoutService
 *
 * @package Drupal\stripe_checkout
 */
class StripeCheckoutService {

  /**
   * The Stripe version used for the Stripe API.
   * This version number must match the version of the installed Stripe library.
   */
  const STRIPE_VERSION = '2019-05-16';

  /**
   * Prefix for subscription store
   */
  const INTL_SUBSCR_ID_PREFIX = 'subscr_';
  /**
   * Database Service Object.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Stripe API service.
   * @var \Drupal\stripe_api\StripeApiService
   */
  protected $stripeApi;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a StripeCheckoutService object.
   */
  public function __construct(Connection $database, StripeApiService $stripeApi) {
    $this->database = $database;
    $this->stripeApi = $stripeApi;
    $this->logger = \Drupal::logger('stripe_checkout');
    $this->initStripeApi();
  }

  /**
   * Initializes the Stripe API with key and version.
   */
  public function initStripeApi() {
    \Stripe\Stripe::setApiKey($this->stripeApi->getApiKey());
    \Stripe\Stripe::setApiVersion(self::STRIPE_VERSION);
  }

  /**
   * @return \Drupal\stripe_api\StripeApiService
   */
  public function getStripeApi() {
    return $this->stripeApi;
  }

  /**
   * Returns the logger with channel stripe_checkout.
   * @return \Psr\Log\LoggerInterface
   */
  public function getLogger() {
    return $this->logger;
  }

  /**
   * Returns the stripe fee for the given amountin Cents / Rappen .
   *
   * @param $amount float   The charged amount.
   * @return float Stripe fee charged with any transaction.
   */
  public function getStripeFee($amount) {
    return round($amount * 0.029) + 30;
  }

  /**
   * Returns the user defined application fee percentage for the clicked stripe button.
   *
   * @param $button_id  string  The clicked button id.
   *
   * @return int
   */
  public function getAppFeePercentage($button_id) {
    // get the related fee button of the clicked checkout button
    $app_fee_percentage = 0;
    $stripe_button_relations = array();
    \Drupal::moduleHandler()->alter('stripe_checkout_fee_button_relation', $stripe_button_relations);

    if (!empty($stripe_button_relations)) {
      // get fee button field name
      $len = strpos($button_id, '--');
      $stripe_button_name = substr($button_id, 0, $len);
      $fee_button_name = $stripe_button_relations[$stripe_button_name];
      $app_fee_percentages = &stripe_checkout_session_data()['fee_buttons'];
      $app_fee_percentage = $app_fee_percentages[$fee_button_name];
    }
    return $app_fee_percentage;
  }

  /**
   * Return the registered user object, if available. Otherwise an Exception is thrown.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface|boolean
   *    Returns the registered user, if any. Otherwise false.
   */
  public function getRegisteredUser() {
    $user = \Drupal::currentUser();
    return $user->isAnonymous() ? false : $user;
  }


  /**
   * Deletes all Stripe customers with the given email address, except the given excluded one if any,
   * and all its Stripe subscriptions. All related Drupal internal subscriptions of the
   * deleted Stripe customers are also deleted.
   *
   * This method guarantees, that only one Stripe customer with the given email
   * address exists containing exactly one subscription.
   *
   * @param $email         string  The email address of a user
   * @param $excl_cust_id  string  A Stripe customer id, which will be excluded
   *                       from deletion.
   *
   * @return boolean  True if customers and subscriptions have been deleted.
   */
  public function deleteCustomerByEmail($email, $excl_cust_id = '') {
    if (!$email) return false;
    try {
      $deleted = 0;
      $customers = \Stripe\Customer::all(["email" => $email])->data;
      foreach ($customers as $customer) {
        if ($excl_cust_id === $customer->id) continue;
        if (!$customer->deleted) {
          // deletes the customer and its subscriptions on the Stripe server
          $customer->delete();
        }
        $deleted += $this->dbDeleteSubscription($customer->id);
      }
      return $deleted > 0;
    }
    catch (Exception $e) {
      $this->logger->error('Error: @error', ['@error' => $e->getMessage()]);
      return false;
    }
  }


  /**
   * For the given registered user the related customer and its subscriptions on the Stripe server
   * are deleted, and all local subscriptions are also deleted.
   *
   * If the Stripe subscription could not be deleted due to a Stripe exception,
   * the local subscription is also not deleted. So the subscription can be deleted later.
   *
   * @param $registered_user  \Drupal\Core\Session\AccountInterface
   *    The registered user object.
   */
  public function deleteUserSubscriptions($registered_user) {
    // do nothing for anonymous user
    if (!$registered_user) return;

    // delete related customer and its subscriptions
    $isDeleted = $this->deleteCustomerByEmail($registered_user->getEmail());
    if ($isDeleted) {
      $args = [
        'user' => $registered_user,
      ];
      \Drupal::moduleHandler()->invokeAll('stripe_checkout_user_unsubscribed', $args);
      $userSubscriptionEvent = new StripeUserSubscriptionEvent($registered_user);
      \Drupal::service('event_dispatcher')->dispatch(StripeUserSubscriptionEvent::USER_UNSUBSCRIBED, $userSubscriptionEvent);
    }
  }


  /**
   * Returns the Stripe subscription plan fitting the given parameters.
   * If the plan does not exist yet, a new subscription plan is created and returned.
   *
   * @param $amount
   * @param $currency
   * @param $recurring_billing
   * @return \Stripe\Plan   Created or retrieved Stripe subscription plan.
   */
  public function getStripePlan($amount, $currency, $recurring_billing) {
    //
    // get subscription plan via plan-id, if available
    try {
      $plan_id = 'bge_' . $recurring_billing . '_' . $amount . '_' . $currency;
      $subscription_plan = \Stripe\Plan::retrieve($plan_id);
      return $subscription_plan;
    }
    catch (Exception $e) {
      // no plan with given id found, do nothing here
    }

    //
    // create a new subscription plan
    switch ($amount) {
      case 300:
        $plan_name = t('Basic Income Silver');
        break;
      case 600:
        $plan_name = t('Basic Income Gold');
        break;
      case 1000:
        $plan_name = t('Basic Income Platinum');
        break;
      default:
        $plan_name = t('Basic Income Personal-@amount', array('@amount' => $amount));
        break;
    }

    $subscription_plan = \Stripe\Plan::create([
      "id" => $plan_id,
      "amount" => $amount,
      "currency" => $currency,
      "interval" => $this->convertRecurringBilling2Interval($recurring_billing),
      "product" => [
        "name" => $plan_name,
      ],
    ]);

    return $subscription_plan;
  }


  /**
   * Get parameters from the internal subscription.
   *
   * @param $subscription   array   Associative array of subscription parameter
   * @param $plan   object  The subscription plan.
   * @return mixed Associative array of charge parameters or false, if Stripe plan could not be retrieved.
   * Associative array of charge parameters or false, if Stripe plan could not be retrieved.
   */
  public function getParamsFromSubscription($subscription, $plan = null) {
    //
    // get charge parameters from subscription
    if (!$subscription) return false;

    try {
      if (!$plan) {
        $plan_id = $subscription['stripe_plan_id'];
        $plan = \Stripe\Plan::retrieve($plan_id);
      }
      $amount = $plan->amount;
      $currency = $plan->currency;
      $stripe_fee = $this->getStripeFee($amount);
      $app_fee = $amount * $subscription['app_fee_percentage'];
      $recurring_billing = $this->convertInterval2RecurringBilling($plan->interval);
      $stripe_cust_id = $subscription['stripe_cust_id'];

      return array(
        'stripe_api_mode' => strtolower($this->stripeApi->getMode()),
        'recurring_billing' => $recurring_billing,
        'currency' => $currency,
        'amount' => ($amount / 100),
        'stripe_fee' => ($stripe_fee / 100),
        'app_fee' => ($app_fee / 100),
        'stripe_cust_id' => $stripe_cust_id,
      );
    }
    catch (Exception $e) {
      return false;
    }
  }


  /**
   * Creates a Stripe checkout session from the given settings.
   *
   * @param $stripe_settings
   *
   * @param $subscription_plan \Stripe\Plan
   *   The Stripe\Plan object
   *
   * @return \Stripe\Checkout\Session
   * @throws \Exception
   */
  public function createStripeCheckoutSession($stripe_settings, $subscription_plan) {
    $amount = $stripe_settings['amount'];
    $currency = $stripe_settings['currency'];
    $recurring_billing = $stripe_settings['recurring_billing'];

    $session_params = [
      "success_url" => $stripe_settings['success_url'],
      "cancel_url" => $stripe_settings['cancel_url'],
      "payment_method_types" => $stripe_settings['payment_method_types'],
      "billing_address_collection" => $stripe_settings['billing_address'],
      "client_reference_id" => $stripe_settings['client_reference_id'],
    ];
    if (isset($stripe_settings['customer_email']) && !empty($stripe_settings['customer_email'])) {
      $session_params["customer_email"] = $stripe_settings['customer_email'];
    }
    if ($recurring_billing === 'one-time') {
      //
      // create line item, if one time billing is set
      $session_params["line_items"] = [[
        'name' => $stripe_settings['name'],
        'description' => $stripe_settings['description'],
        'images' => is_array($stripe_settings['images']) ? $stripe_settings['images'] : [],
        'amount' => $amount,
        'currency' => $currency,
        'quantity' => 1
      ]];
      $session_params["submit_type"] = $stripe_settings['submit_type'];
    }
    else if ($subscription_plan) {
      //
      // subscription with recurring plan
      $session_params["subscription_data"] = [
        'items' => [[
          'plan' => $subscription_plan->id
        ]]
      ];
    }
    else {
      throw new Exception('No subscription plan available for recurring payment.');
    }

    return \Stripe\Checkout\Session::create($session_params);
  }


  /** --------------------------------------------------
   *  Methods for the {pxl_user_stripe_subscription} table
   * --------------------------------------------------*/

  /**
   * Adds a new record to the {pxl_user_stripe_subscription} db.
   *
   * @param $uid                int       User id.
   * @param $plan_id            string    Stripe subscription plan id
   * @param $evt_id             string    The event id.
   * @param $app_fee_percentage float     The chosen fee percentage for the subscription. Default = 0.0.
   * @param $cust_id            string    Stripe customer id if any. Default: empty
   *
   * @return array
   *    Returns the added subscription as associative array.
   * @throws \Exception
   */
  public function dbAddSubscription($uid, $plan_id, $evt_id, $app_fee_percentage = 0.0, $cust_id = '') {
    $fields = array(
      'user_id' => $uid,
      'stripe_cust_id' => $cust_id,
      'stripe_plan_id' => $plan_id,
      'app_fee_percentage' => $app_fee_percentage,
      'stripe_evt_id' => $evt_id,
    );
    $this->database->insert('pxl_user_stripe_subscription')
      ->fields($fields)
      ->execute();

    return $fields;
  }

  /**
   * Gets the internal subscription of the given stripe customer.
   *
   * REMARK: The combination of customer_id and internal subscription id is a unique key because
   * customer id is unique, subscription event id is unique and one of them is always available
   * in a subscription
   *
   * @param $cust_id  string  Stripe Customer id.
   * @param $subscr_intl_id  string  Internal subscription id
   * @return mixed  Associative array with subscription parameters, false otherwise.
   */
  public function dbGetSubscription($cust_id, $subscr_intl_id = '') {
    if ($subscr_intl_id) {
      // occurs only for new subscriptions: get the internal subscription with the given internal subscription id
      $result = $this->database->select('pxl_user_stripe_subscription', 'p')
        ->fields('p', array('stripe_cust_id', 'user_id', 'app_fee_percentage', 'stripe_plan_id', 'stripe_evt_id'))
        ->condition('stripe_evt_id', $subscr_intl_id)
        ->execute()
        ->fetchAssoc();
      return $result;
    }

    // get the subscription for the given stripe customer
    $result = $this->database->select('pxl_user_stripe_subscription', 'p')
      ->fields('p', array('stripe_cust_id', 'user_id', 'app_fee_percentage', 'stripe_plan_id', 'stripe_evt_id'))
      ->condition('stripe_cust_id', $cust_id)
      ->execute()
      ->fetchAssoc();
    return $result;

  }

  /**
   * Updates the internal subscription with the Stripe customer id and evt_id.
   *
   * REMARK: The combination of customer_id and internal subscription id is a unique key because
   * customer id is unique, subscription event id is unique and one of them is always available
   * in a subscription
   *
   * @param        $cust_id        string    The Stripe customer id.
   * @param        $evt_id         string    The Stripe event id.
   * @param        $subscr_intl_id string    Internal subscription id for new payment/subscription
   */
  public function dbUpdateSubscription($cust_id, $evt_id, $subscr_intl_id = '') {
    $fields = array(
      'stripe_cust_id' => $cust_id,
      'stripe_evt_id' => $evt_id,
    );
    if ($subscr_intl_id) {
      // in this case cust_id is not yet updated in subscription
      $this->database->update('pxl_user_stripe_subscription')
        ->fields($fields)
        ->condition('stripe_evt_id', $subscr_intl_id)
        ->execute();
    }
    else if ($cust_id) {
      // cust_id exists already in subscription
      $this->database->update('pxl_user_stripe_subscription')
        ->fields($fields)
        ->condition('stripe_cust_id', $cust_id)
        ->execute();
    }
  }

  /**
   * Deletes the internal subscription with the given stripe_cust_id.
   *
   * @param $stripe_cust_id  string    The Stripe customer ID.
   *
   * @return int Number of rows that has been deleted
   */
  public function dbDeleteSubscription($stripe_cust_id) {
    // delete subscription with given stripe customer (unique)
    $deleted = $this->database->delete('pxl_user_stripe_subscription')
      ->condition('stripe_cust_id', $stripe_cust_id)
      ->execute();
    return $deleted;
  }

  /**
   * Gets all stored subscriptions for the given user id.
   *
   * @param $user_id int Internal user ID (uid)
   * @return mixed    array
   *    Associative array of subscriptions with keys = {'stripe_cust_id', 'app_fee_percentage', 'stripe_plan_id', 'event_id'}
   *    for the given user or empty array.
   */
  public function dbGetUserSubscriptions($user_id) {
    // get related stripe customer for the given user id
    $results = $this->database->select('pxl_user_stripe_subscription', 'p')
      ->fields('p', array('stripe_cust_id', 'app_fee_percentage', 'stripe_plan_id', 'stripe_evt_id'))
      ->condition('p.user_id', $user_id)
      ->execute()
      ->fetchAll(PDO::FETCH_ASSOC);

    return $results;
  }

  /**
   * Cleanup the internal subscriptions for the given user (anonymous or registered).
   * All entries with an empty stripe customer are deleted when they belong to a
   * registered user or are older than 24 hours (anonymous).
   *
   * @param $user_id int Internal user ID (uid)
   * @return int  Number of deleted rows
   */
  public function dbCleanupUserSubscriptions($user_id) {
    $deleted = 0;
    if ($user_id) {
      //
      // delete all internal subscriptions of a registered user without any related stripe customer.
      $deleted = $this->database->delete('pxl_user_stripe_subscription')
        ->condition('user_id', $user_id)
        ->condition('stripe_cust_id', '')
        ->execute();
    }
    else {
      //
      // delete all internal payments and subscription entries older than 24 hours and
      // without any related stripe customer
      $a_day_ago = time() - 86400;
      $subscriptions = $this->dbGetUserSubscriptions(0);
      foreach ($subscriptions as $subscription) {
        if (empty($subscription['stripe_cust_id'])) {
          $evt_id = $subscription['stripe_evt_id'];
          if (strpos($evt_id, self::INTL_SUBSCR_ID_PREFIX) !== 0) continue;
          $subscr_time = str_replace(self::INTL_SUBSCR_ID_PREFIX, '', $evt_id);
          if (intval($subscr_time) < $a_day_ago) {
            $deleted = $this->database->delete('pxl_user_stripe_subscription')
              ->condition('stripe_evt_id', $evt_id)
              ->execute();
          }
        }
      }
    }
    return $deleted;
  }

  /* --------------------------------------------------
   *  Helper methods
   * --------------------------------------------------*/
  /**
   * Converts stripe interval string into recurring payment string.
   */
  protected function convertInterval2RecurringBilling($interval) {
    switch ($interval) {
      case 'day':
        return 'daily';
      case 'week':
        return 'weekly';
      case 'month':
      default:
        return'monthly';
      case 'year':
        return 'yearly';
    }
  }

  /**
   * Converts recurring payment string into stripe interval string.
   */
  protected function convertRecurringBilling2Interval($recurring_billing) {
    switch ($recurring_billing) {
      case 'daily':
        return 'day';
      case 'weekly':
        return 'week';
      case 'monthly':
      default:
        return 'month';
      case 'yearly':
        return 'year';
    }
  }

}
