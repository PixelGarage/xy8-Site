<?php

/**
 * Implements hook_token_info_alter().
 */
function onboarding_token_info_alter(&$data) {
  $test = $data;
  if (isset($data['tokens']['webform_submission'])) {
    $data['tokens']['webform_submission']['onboarding_user'] = [
      'name' => t('Current on-boarding user'),
      'description' => t('The user that currently is in the on-boarding process.'),
      'type' => 'user',
    ];
  }
}

function onboarding_tokens($type, $tokens, array $data, array $options, \Drupal\Core\Render\BubbleableMetadata $bubbleable_metadata) {
  $replacements = [];

  if ($type === 'webform_submission' && !empty($data['webform_submission'])) {
    $token_service = \Drupal::token();
    $webform_submission = $data['webform_submission'];

    /* Chained token relationships. */
    if (($user_tokens = $token_service->findWithPrefix($tokens, 'onboarding_user')) && ($user = $webform_submission->onboarding_user)) {
      $replacements += $token_service->generate('user', $user_tokens, ['user' => $user], $options, $bubbleable_metadata);
    }
  }

  return $replacements;
}
