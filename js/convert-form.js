(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.preventFormSubmit = {
    attach: function (context, settings) {
      $('.vyva-convert-form input[type=text]')
        .once('prevent-form-submit')
        .on('keypress', function (e) {
          if (e.charCode === 13) {
            return false;
          }
        });
    }
  };

})(jQuery, Drupal);
