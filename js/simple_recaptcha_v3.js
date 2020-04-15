(function($) {
  "use strict";
  Drupal.behaviors.simple_recaptcha_v3 = {
    attach: function(context, drupalSettings) {
      // Grab form IDs from settings and loop through them.
       for( let formId in drupalSettings.simple_recaptcha_v3.forms) {
        const $form = $('form[data-recaptcha-id="'+formId+'"]');
          let formSettings = drupalSettings.simple_recaptcha_v3.forms[formId];
          $form.once("simple-recaptcha").each(function() {
          $form.find('input[name="simple_recaptcha_score"]').val(formSettings.score);

          // Disable submit buttons on form.
          const $submit = $form.find('[type="submit"]');
          $submit.attr("data-disabled", "true");
          const $captcha = $(this).closest("form").find(".recaptcha-wrapper");
          const captchas = [];

          // AJAX forms - add submit handler to form.beforeSend.
          // Update Drupal.Ajax.prototype.beforeSend only once.
          if (typeof Drupal.Ajax !== 'undefined' && typeof Drupal.Ajax.prototype.beforeSubmitSimpleRecaptchaOriginal === 'undefined') {
            Drupal.Ajax.prototype.beforeSubmitSimpleRecaptchaOriginal = Drupal.Ajax.prototype.beforeSubmit;
            Drupal.Ajax.prototype.beforeSubmit = function (form_values, element_settings, options) {
              let $token = $form.find('input[name="simple_recaptcha_token"]').val();
              if ($token === 'undefined' || $token === '') {
                this.ajaxing = false;
                return false;
              }
              return this.beforeSubmitSimpleRecaptchaOriginal();
            }
          }

          $submit.on("click", function(e) {
            if ($(this).attr("data-disabled") === "true") {
              // Get HTML IDs for further processing.
              const formHtmlId = $form.attr("id");
              const submitHtmlId = $(this).attr("id");

              // Find captcha wrapper.
              const $captcha = $(this).prev(".recaptcha-v3-wrapper");

              if ( typeof captchas[formHtmlId] === "undefined" ) {
                e.preventDefault();
                $captcha.hide();
                grecaptcha.ready(function() {
                  captchas[formHtmlId] = grecaptcha.execute(drupalSettings.simple_recaptcha_v3.sitekey, {action: formSettings.action}).then(function(token){
                    const $currentSubmit = $('#' + submitHtmlId);
                    $form.find('input[name="simple_recaptcha_token"]').val(token);
                    $form.find('input[name="simple_recaptcha_message"]').val(formSettings.error_message);
                    $currentSubmit.removeAttr("data-disabled");
                    // Click goes for regular forms, mousedown for AJAX forms.
                    $currentSubmit.click();
                    $currentSubmit.mousedown();
                  });
                });
              }
              e.preventDefault();
            }
          });
        });
      }
    }
  };
})(jQuery);
