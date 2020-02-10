<?php

namespace Drupal\stripe_checkout\Plugin\Field\FieldFormatter;


use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\DecimalFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'stripe_checkout_button_fee' formatter.
 *
 * The 'stripe_checkout_button_fee' formatter displays an application fee selection button,
 * that allows to select a fee percentage out of the given 'decimal_number' values.
 *
 * @FieldFormatter(
 *   id = "stripe_checkout_button_fee",
 *   label = @Translation("Stripe checkout button - App fee percentage"),
 *   field_types = {
 *     "decimal",
 *     "float"
 *   }
 * )
 */
class StripeCheckoutFeePercentageButton extends DecimalFormatter {
  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    $parent_settings = parent::defaultSettings();
    $parent_settings['prefix_suffix'] = FALSE;

    return [
        'default_index' => '0',
        'top_text' => '',
        'bottom_text' => '',
        'stripe_fee_text' => '',
      ] + $parent_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);
    $elements['prefix_suffix']['#disabled'] = TRUE;
    $elements['thousand_separator']['#weight'] = 9;

    $elements['default_index'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Default button index'),
      '#default_value' => $this->getSetting('default_index'),
      '#description'   => t('Define button index, that is selected at display time. Default: 0'),
    );
    $elements['top_text'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Top text'),
      '#default_value' => $this->getSetting('top_text'),
      '#description'   => t('Define the text, that is displayed above the fee radio buttons. Default: none'),
    );
    $elements['bottom_text'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Bottom text'),
      '#default_value' => $this->getSetting('bottom_text'),
      '#description'   => t('Define the text, that is displayed below the fee radio buttons. Default: none'),
    );
    $elements['stripe_fee_text'] = array(
      '#type'          => 'textfield',
      '#title'         => t('Stripe fee text'),
      '#default_value' => $this->getSetting('stripe_fee_text'),
      '#description'   => t('Define the text, that informs about the regular Stripe transaction fees. Default: none'),
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getFieldSettings();
    $summary = parent::settingsSummary();

    $summary[] = t('Default button index:@text', array('@text' => $settings['default_index']));
    $summary[] = t('Top text:            @text', array('@text' => $settings['top_text']));
    $summary[] = t('Bottom text:         @text', array('@text' => $settings['bottom_text']));
    $summary[] = t('Stripe fee text:     @text', array('@text' => $settings['stripe_fee_text']));

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $field_name = $this->fieldDefinition->getName();
    $settings = $this->getSettings();

    // set button specific values
    $button_id = Html::cleanCssIdentifier($field_name);
    $fees = [];
    foreach ($items as $delta => $item) {
      $fees[$delta] = $this->numberFormat($item->value);
    }
    $element[0] = [
      '#theme' => 'stripe_checkout_fee_percentage',
      '#field_id' => $button_id,
      '#fee_items' => $fees,
      '#default_button_index' => $settings['default_index'],
      '#top_text' => $settings['top_text'],
      '#bottom_text' => $settings['bottom_text'],
      '#stripe_fee_text' => $settings['stripe_fee_text'],
    ];

    return $elements;
  }

}
