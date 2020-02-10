The stripe_checkout module provides field formatters for the number (decimal) field that can display one or more
Stripe Checkout buttons with a predefined or customizable amount in one of the given currencies.

To use this Drupal module for Test and Live payments, you need to open a Stripe account (https://stripe.com/global).


See also https://stripe.com/docs/payments/checkout

-----------------------------------------------------------------------------
VERSION INFO
-----------------------------------------------------------------------------
Version 1.x implements the Stripe Checkout process supporting Strong Customer Authentication (SCA).
This module is tested with the Stripe PHP API library version 6.40.0 - 2019-06-27.


-----------------------------------------------------------------------------
ABOUT THIS MODULE
-----------------------------------------------------------------------------
This module provides an implementation for two types of a Stripe checkout button and a application fee selection button.

1) Fixed value stripe checkout button
Displays a fixed value with currency as chargeable amount. Clicking this button opens the Stripe Checkout dialog
with the given amount and currency already set, ready to be paid right away.

2) Custom value stripe checkout button
Displays a submit button with a text field to add a user defined amount in the given currency. Clicking the submit
button opens the Stripe Checkout dialog with the user specified amount in the given currency.

3) A third field formatter creates an application fee button, which can display different application fee percentages
selectable by the user. Each fee button can be related to a checkout button, so that the correct application fee can be
evaluated for a payment.


These buttons are available as:
- Themes, see 'stripe_checkout_button-fix',  'stripe_checkout_button-custom' and 'stripe_checkout_fee_percentage' themes

- Field formatters for the number decimal field. Add a decimal field to a content type and choose one of the given Stripe
	field formatters provided by this module. These formatters allow to theme a checkout or fee button which then is displayed
	with the given decimal value and the given currency.



-----------------------------------------------------------------------------
MIGRATION FROM STRIPE_BUTTON MODULE TO THIS MODULE
-----------------------------------------------------------------------------
The migration from the stripe_button module to this new stripe_checkout module can be done easily with the following steps:
1) Export ONLY the data of the pxl_user_stripe_subscription table in the default Drupal db.
2) Uninstall the stripe_button module. This must be done before the next step.
3) Install the stripe_checkout module (this module)
4) Import the exported data into the new and empty table pxl_user_stripe_subscription (table name and structure are the same).
5) All subscribed user and its related customers on the Stripe server are available again.


-----------------------------------------------------------------------------
INSTALLATION
-----------------------------------------------------------------------------
Stripe API Library:
This module requires the Stripe PHP API library. Follow these steps to create the library:

1. Install and enable Libraries API module (see https://www.drupal.org/project/libraries).
2. Create a stripe folder in the libraries directory (sites/all/libraries) and download the Stripe PHP library from GitHub in it.
3. Download and install the stripe_api and the stripe_checkout (this module) modules.
4. Configure the Stripe account access on the configuration page of the Stripe API (/admin/config/services/stripe_api).
5. Add a decimal number field to one of your content types and configure it in the view mode.
6. Set one of the two field formatters (Predefined or Custom value button) and configure more options for the Checkout dialog
    - the currency
    - the dialog title
    - the dialog description
    - the dialog button label (the amount and currency are always added at the end of the defined label)
    - if a billing address form should be displayed
    - if a shipping address form should be displayed
    - if one click payment (remember me) should be enabled


-----------------------------------------------------------------------------
Test webhook with Localtunnel tool locally
-----------------------------------------------------------------------------

The Localtunnel tool (https://localtunnel.github.io/www/) can be used to test webhooks locally.
The tool can be installed with the Node Package Manager (npm):

npm install -g localtunnel

Assuming your local Drupal server is running on port 80, just use the lt command to start the tunnel.

lt --subdomain "stripe-checkout" --port 80

Thats it! The tool will connect to the tunnel server, setup the tunnel, and tell you what url to use for your testing.
The tunnel IP will always be the same, if you define a subdomain, e.g. https://stripe-checkout.localtunnel.me

1. Add the webhook url https://stripe-checkout.localtunnel.me/stripe/webhook to the Stripe webhook page
   that contains the test mode settings (https://dashboard.stripe.com/test/webhooks) and define all events
   to be sent to this webhook.
2. To debug the received events, add the session id to the webhook url, e.g.
   https://stripe-checkout.localtunnel.me/stripe/webhook/?XDEBUG_SESSION_START=10240.
3. Close the terminal session to close the tunnel after testing (or use Ctrl+c in Terminal).


-----------------------------------------------------------------------------
CREDITS
-----------------------------------------------------------------------------
This project uses the really great Payment Gateway of Stripe, a new era of payment, simple, secure and fast.

Sponsored by Pixelgarage (http://www.pixelgarage.ch)
