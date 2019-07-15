(function($) {
  "use strict";
  Drupal.behaviors.simple_recaptcha = {
    attach: function(context, drupalSettings) {
      const verified = [];
      // Grab form IDs from settings and loop through them.
       for(let formId in drupalSettings.simple_recaptcha.form_ids) {
         const $form = $('form[data-recaptcha-id="'+formId+'"]');

         $form.once("simple-recaptcha").each(function() {
          // Disable submit buttons on form.
          const $submit = $form.find('[type="submit"]');
          $submit.attr("data-disabled", "true");

          const formHtmlId = $form.attr("id");
          const captchas = [];

          // Track verified captchas
          verified[formHtmlId] = false;

          $submit.on("click", function(e) {
            if ($(this).attr("data-disabled") === "true") {
              // Get HTML IDs for further processing.


              const submitHtmlId = $(this).attr("id");

              // Find captcha wrapper.
              const $captcha = $(this).prev(".recaptcha-wrapper");

              // If it is a first submission of that form, render captcha widget.
              if (
                $captcha.length &&
                typeof captchas[formHtmlId] === "undefined"
              ) {
                captchas[formHtmlId] = grecaptcha.render($captcha.attr("id"), {
                  sitekey: drupalSettings.simple_recaptcha.sitekey
                });
                $captcha.fadeIn();
                $captcha.addClass('recaptcha-visible');
                e.preventDefault();
              } else {
                // Check reCaptcha response.
                const response = grecaptcha.getResponse(captchas[formHtmlId]);

                // Verify reCaptcha response.
                if (typeof response !== "undefined" && response.length ) {
                  e.preventDefault();
                  const $currentSubmit = $('#' + submitHtmlId);
                  $form.find('input[name="simple_recaptcha_token"]').val(response);
                  $currentSubmit.removeAttr("data-disabled");
                  $currentSubmit.trigger("click");
                } else {
                  // Mark captcha widget with error-like border.
                  $captcha.children().css({
                    "border": "1px solid #e74c3c",
                    "border-radius": "4px"
                  });
                  $captcha.addClass('recaptcha-error');
                  e.preventDefault();
                }
              }
            }
          });
        });
      }
    }
  };
})(jQuery);
