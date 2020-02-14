/**
 * @file
 * Extends the Drupal paragraphs functionality..
 */


(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.paragraphSlideshow = {
    attach: function (context, settings) {
      window.onload = function () {
        jQuery('.carousel').carousel('dispose');
        jQuery('.carousel').carousel({
          interval: 5000,
          keyboard: true,
          touch: true,
          pause: 'false',
          ride: false,
        });
/*        jQuery('.carousel').carousel('pause');*/
      };

      jQuery('#playButton').click(function () {
        jQuery('.carousel').carousel('cycle');
      });

      jQuery('#pauseButton').click(function () {
        jQuery('.carousel').carousel('pause');
      });

      jQuery('.carousel-control-prev').click(function () {
        jQuery('.carousel').carousel('prev');
      });

      jQuery('.carousel-control-next').click(function () {
        jQuery('.carousel').carousel('next');
      });

      //Remueve la funcionalidad de slider en las vista news
      //jQuery('.display-none-controllers .carousel').carousel('dispose');
      jQuery('.display-none-controllers .carousel').removeClass('carousel');

    }
  };
})(jQuery, Drupal, drupalSettings);

jQuery('.alert-leave-page').click(function (event) {
});
