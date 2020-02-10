/**
 * Created by ralph on 18.05.16.
 */
(function ($, Drupal, drupalSettings) {

  /**
   * Handles the fee selection boxes.
   *
   */
  Drupal.behaviors.stripeButtonFees = {
    attach: function () {
      var $fee_containers = $('.stripe-button-fee-percentages');

      $fee_containers.once('fee-container', function() {
        var $container = $(this),
          button_id = $container.attr('id'),
          $fee_radios = $container.find('.fee-radio'),
          $selectedItem = $container.find('.fee-radios-wrapper .selected');

        $fee_radios.each(function(index) {
          var $this = $(this);

          $this.on('click', function() {
            var $feeAnswerWrapper = $container.find('.fee-answer-wrapper'),
              feeValue = parseFloat($this.attr('data-fee-value')),
              params = {
                feeButtonID: button_id,
                selectedFeePercentage: feeValue
              };

            // toggle selection
            $selectedItem.removeClass('selected');
            $this.addClass('selected');
            $selectedItem = $this;

            // update selected fee on server
            $feeAnswerWrapper.load('/stripe/checkout/fee', params);
          });
        });

      });

    }
  };

})(jQuery, Drupal, drupalSettings);

