/**
 * @file
 * Placeholder file for custom sub-theme behaviors.
 *
 */
(function ($, Drupal) {

  /**
   * Use this behavior as a template for custom Javascript.
   */
  Drupal.behaviors.exampleBehavior = {
    attach: function (context, settings) {
      //alert("I'm alive!");
    }
  };

  Drupal.behaviors.fixedMenu = {
    attach: function (context, settings) {

      var $header = $('.l-header', context);
      $(window, context).scroll(function() {

        if($(document).scrollTop() > 0) {
          if($header.css('position') === 'fixed') {
            $header.addClass('small');
          }
        } else {
          $header.removeClass('small');
        }

      });
    }
  };

})(jQuery, Drupal);
