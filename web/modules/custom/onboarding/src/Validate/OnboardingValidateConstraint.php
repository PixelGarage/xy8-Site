<?php


namespace Drupal\onboarding\Validate;

use Drupal\Core\Form\FormStateInterface;

/**
 * Form API callback. Validate element value.
 */
class OnboardingValidateConstraint {
  /**
   * Validates given element.
   *
   * @param array              $element      The form element to process.
   * @param FormStateInterface $formState    The form state.
   * @param array              $form The complete form structure.
   */
  public static function validate(array &$element, FormStateInterface $formState, array &$form) {
    $error = false;
    $error_msg = '';
    $webformKey = $element['#webform_key'];
    $value = $formState->getValue($webformKey);


    // Skip empty unique fields or arrays (aka #multiple).
    if ($value === '' || is_array($value)) {
      return;
    }

    // validate the field value
    switch ($element['#webform_id']) {
      case 'start_onboarding--name':
        // validate user name
        $error_msg = user_validate_name($value);
        $error = !is_null($error_msg);
        break;
      case 'iban_check--submitted_amount':
        // check if value equals the submitted amount
        // get onboarding user and submitted amount
        $onboarding_session = &onboarding_session_data();
        $user = $onboarding_session['onboarding_user'];
        $status = intval($user->field_onboarding_status->value);
        $submitted_amount = ($status < 0) ? (-$status) / 100 : null;
        $value = floatval($value);
        if ($value != $submitted_amount) {
          $error = true;
          $error_msg = t('The value %value is not the amount we transferred to your account. Please try again or contact us for further instructions.', ['%value' => $value]);
        }
        break;
    }


    // set validation error for element, if any
    if ($error) {
      if ($error_msg) {
        $formState->setError($element, $error_msg);
      }
      else if (isset($element['#title'])) {
        $tArgs = array(
          '%name' => empty($element['#title']) ? $element['#parents'][0] : $element['#title'],
          '%value' => $value,
        );
        $formState->setError(
          $element,
          t('The value %value is not allowed for element %name. Please use a different value.', $tArgs)
        );
      } else {
        $formState->setError($element);
      }
    }
  }
}
