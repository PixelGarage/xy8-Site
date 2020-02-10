<?php

namespace Drupal\stripe_checkout\Plugin\Field\FieldFormatter;


use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\DecimalFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'stripe_checkout_button_fix' formatter.
 *
 * The 'stripe_checkout_button_fix' formatter displays a fixed value Stripe checkout button,
 * that opens the Stripe checkout dialog to pay the 'decimal_number' amount in
 * the given currency via credit card.
 *
 * @FieldFormatter(
 *   id = "stripe_checkout_button_fix",
 *   label = @Translation("Stripe checkout button - fixed value"),
 *   field_types = {
 *     "decimal",
 *     "float"
 *   }
 * )
 */
class StripeCheckoutFixedValueButton extends DecimalFormatter {
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $parent_settings = parent::defaultSettings();
    $parent_settings['prefix_suffix'] = FALSE;

    return [
        'recurring_billing' => 'one-time',
        // button box
        'box_title' => '',
        'box_text' => '',
        // stripe checkout dialog
        'name' => '',
        'description' => '',
        'currency' => 'CHF',
        'submit_type' => 'auto',
        'billing_address' => FALSE,
      ] + $parent_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['prefix_suffix']['#disabled'] = TRUE;
    $elements['thousand_separator']['#weight'] = 9;

    // define available currencies
    $currencies = array(
      'CHF'  => t('CHF'),
      'EUR' => t('EUR'),
      'USD' => t('USD'),
      'GBP' => t('GBP'),
      'DKK' => t('DKK'),
      'NOK' => t('NOK'),
      'SEK' => t('SEK'),
      'AUD' => t('AUD'),
      'CAD' => t('CAD'),
    );

    // define available intervals for recurring billing
    $intervals = array(
      'one-time' => t('Once'),
      'daily' => t('Daily'),
      'weekly' => t('Weekly'),
      'monthly' => t('Monthly'),
      'yearly' => t('Per year'),
    );

    $submit_types = array(
      'auto' => t('Auto'),
      'pay' => t('Pay'),
      'book' => t('Book'),
      'donate' => t('Donate'),
    );

    // define formatter settings form
    $elements['recurring_billing'] = array(
      '#type'          => 'select',
      '#title'         => t('Recurring billing'),
      '#options' => $intervals,
      '#default_value' => $this->getSetting('recurring_billing'),
      '#description'   => t('Defines the frequency with which the amount is settled. Default: Once'),
    );
    $elements['box_title'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Box title'),
      '#default_value' => $this->getSetting('box_title'),
      '#description'   => t('Define the title of the button box. Default: Donate'),
    );
    $elements['box_text'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Box text'),
      '#default_value' => $this->getSetting('box_text'),
      '#description'   => t('Define the descriptive text of the button box. Default: none'),
    );
    $elements['currency'] = array(
      '#type' => 'select',
      '#title' => t('Currency'),
      '#options' => $currencies,
      '#default_value' => $this->getSetting('currency'),
    );
    $elements['name'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Item name'),
      '#default_value' => $this->getSetting('name'),
      '#description'   => t('Stripe checkout dialog: Item name to be purchased or site name. Default: site name'),
    );
    $elements['description'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Description'),
      '#default_value' => $this->getSetting('description'),
      '#description'   => t('Stripe checkout dialog: Describe the item to be purchased. Default: none'),
    );
    $elements['submit_type'] = array(
      '#type'          => 'select',
      '#title'         => t('Submit type'),
      '#options' => $submit_types,
      '#default_value' => $this->getSetting('submit_type'),
      '#description'   => t("Stripe checkout dialog: Define the submit type of the Stripe button (Auto, Pay, Book, Donate). Default: auto"),
    );
    $elements['billing_address'] = array(
      '#type' => 'checkbox',
      '#title' => t("Collect user's billing address, if checked."),
      '#default_value' => $this->getSetting('billing_address'),
      '#description'   => t("Stripe checkout dialog: Specify whether Checkout should collect the user's billing address. Default: false"),
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $summary[] = t('Recurring billing:    @recur', array('@recur' => $this->getSetting('recurring_billing')));
    $summary[] = t('Box title:            @title', array('@title' => $this->getSetting('box_title')));
    $summary[] = t('Box text:             @text', array('@text' => $this->getSetting('box_text')));
    $summary[] = t('Currency:             @curr', array('@curr' => $this->getSetting('currency')));
    $summary[] = t('Stripe checkout dialog settings:');
    $summary[] = t('Item or company name: @name', array('@name' => $this->getSetting('name')));
    $summary[] = t('Item description:     @desc', array('@desc' => $this->getSetting('description')));
    $summary[] = t('Billing address:      @desc', array('@desc' => ($this->getSetting('billing_address') == 1) ? 'required' : 'auto'));
    $summary[] = t('Button submit type:   @curr', array('@curr' => $this->getSetting('submit_type')));

    return $summary;
  }

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
      $elements[$delta] = [
        '#theme' => 'stripe_checkout_button_fix',
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
