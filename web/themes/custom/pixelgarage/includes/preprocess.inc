<?php
/**
 * Created by PhpStorm.
 * User: ralph
 * Date: 06.02.15
 * Time: 17:23
 */

use Drupal\Core\Template\Attribute;

function pixelgarage_preprocess_html(&$vars) {
  //
  // add header position class to body
  $vars['attributes']['class'][] = 'header-' . theme_get_setting('bootstrap_barrio_header_position');
}

/**
 * Override or insert variables for the page templates.
 */
function pixelgarage_preprocess_page(&$vars) {
  //
  // declare attributes for header
  $vars['header_attributes'] = [
    'class' => [ 'header', theme_get_setting('bootstrap_barrio_header_position') ]
  ];
  $vars['header_attributes'] = new Attribute($vars['header_attributes']);

  // set logo (language dependant) and site name
  $language = \Drupal::languageManager()->getCurrentLanguage();
  $path = drupal_get_path('theme', 'pixelgarage');
  $logo = $path . '/images/deinbge_logo_d.png';
  $vars['logo'] = $logo ? file_create_url($logo) : '';
  $vars['site_name'] = ''; //\Drupal::config('system.site')->get('name');
  switch ($language->getId()) {
    case 'en':
      $vars['logo'] = str_replace('_d.png', '_e.svg', $vars['logo']);
      break;
    case 'fr':
      $vars['logo'] = str_replace('_d.png', '_f.svg', $vars['logo']);
      break;
    case 'it':
      $vars['logo'] = str_replace('_d.png', '_i.svg', $vars['logo']);
      break;
    case 'de':
    default:
      $vars['logo'] = str_replace('.png', '.svg', $vars['logo']);
      break;
  }
}


/**
 * PARAGRAPH variables: switch, full-width and anchor
 * Override or insert variables for the paragraph templates.
 */
function pixelgarage_preprocess_bs_1col(&$vars) {
  _paragraph_additional_variables($vars);
}

function pixelgarage_preprocess_bs_2col(&$vars) {
  _paragraph_additional_variables($vars);
}

function _paragraph_additional_variables(&$vars) {
  $content = $vars['content'];
  if ($content['#entity_type'] == 'paragraph') {
    // type hint for PHPStorm code completion
    // @var $paragraph \Drupal\Core\Entity\ContentEntityBase
    $paragraph = $content['#paragraph'];
    if ($paragraph->hasField('field_switch')) {
      $vars['switch'] = $paragraph->get('field_switch')->value == '1';
    }
    if ($paragraph->hasField('field_full_width')) {
      $vars['full_width'] = $paragraph->get('field_full_width')->value == '1';
    }
    if ($paragraph->hasField('field_anchor')) {
      $vars['anchor'] = $paragraph->get('field_anchor')->value;
    }
  }
}

