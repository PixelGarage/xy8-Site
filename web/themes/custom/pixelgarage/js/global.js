/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  /**
   * Converts headline items when document is loaded
   */
  Drupal.behaviors.headline = {
    attach: function() {
      $(document).ready(function () {
        // Headline, surrounds each word with a span-tag
        $('.headline').each(function () {
          var text = $(this).text().split(' ');
          for (var i = 0, len = text.length; i < len; i++) {
            text[i] = '<span class="headline-inner">' + text[i] + '</span>';
          }
          $(this).html(text.join(' '));
        });
      });
    }
  };


})(jQuery, Drupal);
