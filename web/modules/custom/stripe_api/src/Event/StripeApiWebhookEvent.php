<?php

namespace Drupal\stripe_api\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Class StripeApiWebhookEvent.
 *
 * Provides the Stripe API Webhook Event.
 */
class StripeApiWebhookEvent extends Event {

  const WEBHOOK = 'stripe_api.webhook';

  /**
   * @var string
   */
  public $type;
  /**
   * @var object
   */
  public $data;
  /**
   * @var \Stripe\Event
   */
  public $event;

  /**
   * Sets the default values for the event.
   *
   * @param string $type
   *   Webhook event type.
   * @param array $data
   *   Webhook event data.
   * @param \Stripe\Event $event
   *   Stripe event object.
   */
  public function __construct($type, $data, \Stripe\Event $event = NULL) {
    $this->type = $type;
    $this->data = $data;
    $this->event = $event;
  }

}
