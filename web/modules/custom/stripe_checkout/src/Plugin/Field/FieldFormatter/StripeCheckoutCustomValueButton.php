<?php

namespace Drupal\stripe_checkout\Plugin\Field\FieldFormatter;


use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Plugin implementation of the 'stripe_checkout_button_custom' formatter.
 *
 * The 'stripe_checkout_button_custom' formatter displays a custom value Stripe checkout button,
 * that first allows the user to set an arbitrary value before it opens
 * the Stripe checkout dialog to pay the given 'decimal_number' amount in
 * the given currency via credit card.
 *
 * @FieldFormatter(
 *   id = "stripe_checkout_button_custom",
 *   label = @Translation("Stripe checkout button - custom value"),
 *   field_types = {
 *     "decimal",
 *     "float"
 *   }
 * )
 */
class StripeCheckoutCustomValueButton extends StripeCheckoutFixedValueButton {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $field_name = $this->fieldDefinition->getName();
    $settings = $this->getSettings();

    foreach ($items as $delta => $item) {
      // set button specific values
      $button_id = Html::cleanCssIdentifier($field_name . '--' . $delta);
      $amount = $this->numberFormat($item->value);

      // create Stripe checkout button render array
      // create button render array
      $elements[$delta] = [
        '#theme' => 'stripe_checkout_button_custom',
        '#button_id' => $button_id,
        '#box_title' =>  $settings['box_title'],
        '#box_text' =>  $settings['box_text'],
        '#amount' => $amount,
        '#currency' => $settings['currency'],
        '#stripe_settings' => $settings,
        '#csp' => false,
      ];
    }

    return $elements;
  }

}
