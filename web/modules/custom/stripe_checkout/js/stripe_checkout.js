/**
 * This file contains behaviors for the stripe checkout.
 *
 * Created by ralph on 31.01.15.
 */

(function ($, Drupal, drupalSettings) {

  /**
   * Handles the Stripe checkout process with Strong Customer Authentication (SCA).
   * A button click initiates a checkout session creation on the server and use the
   * returned session ID to redirect to the Stripe checkout process.
   * This script handles all Stripe buttons with a fixed value.
   *
   * @see https://stripe.com/docs/payments/checkout/server
   */
  Drupal.behaviors.stripeCheckoutFixedButton = {
    attach: function () {
      var $clickedButton,
          stripeCheckout = drupalSettings.stripe_checkout;

      // Iterate through all defined checkout buttons
      $.each(stripeCheckout.checkout_buttons, function (buttonID, settings) {
        var $button = $('#' + buttonID),
            $buttonContainer = $button.parents('.stripe-button-container');

        $button.off('click');
        $button.on('click', function (e) {
          e.preventDefault();
          // set clicked button
          $clickedButton  = $(this);
          $clickedButton.prop("disabled", true);

          // get checkout session id
          $.post('/stripe/checkout/session', { btnID: buttonID }, function (response, status, xhr) {
            if (status == "error") {
              var msg = "Server error " + xhr.status + ": " + xhr.statusText;
              $buttonContainer.append('<div class="stripe-button-error" style="color: indianred">' + msg + '</div>');
            }
            else {
              // open Stripe Checkout page
              var stripe = Stripe(stripeCheckout.stripe_public_key);

              if (response.code === 200 && response.session_id) {
                // redirect to checkout
                stripe.redirectToCheckout({
                  sessionId: response.session_id
                }).then(function (result) {
                  if (result.error) {
                    $buttonContainer.append('<div class="stripe-button-error" style="color: indianred">' + result.error.message + '</div>');
                  }
                  else {
                    $buttonContainer.append('<div class="stripe-button-success">' + 'Your payment was successful' + '</div>');
                    //$clickedButton.prop("disabled", false);
                  }
                });
              }
              else {
                var msg = response.message ? response.message : "Checkout cannot be performed due to incomplete data.";
                $buttonContainer.append('<div class="stripe-button-error" style="color: indianred">' + msg + '</div>');
              }
            }
          });

        });
      });
    }
  };

  /**
   * Handles all custom form buttons.
   * After the user has entered an amount, the stripe button with this amount is requested from the server
   * and immediately clicked to start the Stripe Checkout process (see fixed value button above).
   *
   * @see https://stripe.com/docs/checkout#integration-custom
   */
  Drupal.behaviors.stripeCheckoutCustomButton = {
    attach: function () {
      // Iterate through all defined stripe custom buttons
      $.each(drupalSettings.stripe_checkout.custom_buttons, function (buttonID, settings) {
        var $form_submit = $('#form-' + buttonID + ' .form-submit'),
          $form_text = $('#form-' + buttonID + ' .form-text'),
          $buttonContainer = $form_submit.parents('.stripe-button-container');

        $form_submit.off('click');
        $form_submit.on('click', function (e) {
          e.preventDefault();

          var new_amount = $form_text.val(),
            params = {
              btnID: buttonID,  // used to recreate button in ajax response
              newAmount: new_amount
            };

          //
          // get the stripe button with the user set value
          $buttonContainer.load('/stripe/checkout/custom_button', params, function (response, status, xhr) {
            if (status == "error") {
              var msg = "Server error " + xhr.status + ": " + xhr.statusText;
              $buttonContainer.append('<div class="stripe-button-error">' + msg + '</div>');
            }
            else {
              // attach behaviours to new stripe button
              Drupal.attachBehaviors($buttonContainer);

              // immediately create Stripe Checkout session and open checkout page
              $('#' + buttonID).click();
            }
          });

        });
      });

    }
  };


})(jQuery, Drupal, drupalSettings);

