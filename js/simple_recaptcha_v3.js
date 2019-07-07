(function($) {
  "use strict";
  Drupal.behaviors.simple_recaptcha_v3 = {
    attach: function(context, drupalSettings) {
      // Grab form IDs from settings and loop through them.
       for( let formId in drupalSettings.simple_recaptcha_v3.forms) {
        const $form = $('form[data-recaptcha-id="'+formId+'"]');
          let formSettings = drupalSettings.simple_recaptcha_v3.forms[formId];
          $form.once("simple-recaptcha").each(function() {
          // Disable submit buttons on form.
          const $submit = $form.find('[type="submit"]');
          $submit.attr("data-disabled", "true");
          const $captcha = $(this).prev(".recaptcha-wrapper");
          const captchas = [];

          $submit.on("click", function(e) {
            if ($(this).attr("data-disabled") === "true") {
              // Get HTML IDs for further processing.
              const formHtmlId = $form.attr("id");
              const submitHtmlId = $(this).attr("id");

              // Find captcha wrapper.
              const $captcha = $(this).prev(".recaptcha-v3-wrapper");
              // If it is a first submission of that form, render captcha widget.
              if ( typeof captchas[formHtmlId] === "undefined" ) {
                $captcha.hide();
                grecaptcha.ready(function() {
                  captchas[formHtmlId] = grecaptcha.execute(drupalSettings.simple_recaptcha_v3.sitekey, {action: formSettings.action}).then(function(token){
                    $.post("/api/simple_recaptcha/verify?recaptcha_type=v3&recaptcha_response=" + token + "&recaptcha_site_key=" + drupalSettings.simple_recaptcha_v3.sitekey ).done(
                        function(data){
                          const score = data.score * 100;
                          if (data.success && score >= formSettings.score ) {
                            // Unblock submit on success.
                            const $currentSubmit = $('#' + submitHtmlId);
                            $captcha.addClass('recaptcha-success');
                            $currentSubmit.removeAttr("data-disabled");
                            $currentSubmit.trigger("click");
                          } else {
                            // Trigger error.
                            $captcha.fadeIn();
                            $captcha.addClass('recaptcha-error');
                            $captcha.text(formSettings.error_message);
                            e.preventDefault();
                          }
                        }
                    );
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
