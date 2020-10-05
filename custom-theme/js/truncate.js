/**
 * @file
 * Truncated behavior for text.
 *
 */
(function ($, Drupal) {

  /**
   * Use this behavior as a template for custom Javascript.
   */
  Drupal.behaviors.trancate = {
    attach: function (context, settings) {
      var $truncated = $('.js-truncated');
      $truncated.each(function() {
        var $trigger = $(this).next();

        $trigger.once().click(function() {
          if ($(this).hasClass('revealed')) {
            $(this).prev().removeClass('revealed');
            $(this).text('Show More').removeClass('revealed');
          }
          else {
            $(this).prev().addClass('revealed');
            $(this).text('Show Less').addClass('revealed');
          }
        });
      });
    }
  };

})(jQuery, Drupal);
