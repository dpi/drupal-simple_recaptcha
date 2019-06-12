# Simple Google reCatcpah
This Drupal 8 module provides reCAPTCHA v2 integration, it is crafted to work with different caching strategies, with multiple forms displayed on the same page.

# Installation 
Run composer to install the module:
```
$ composer require drupal/simple_recaptcha:^1.0
```

# Configuration 
Navigate to /admin/config/services/simple_recaptcha and setup you site key and secret key. 
Both keys can be found in [Google reCaptcha admin dashboard](https://www.google.com/recaptcha/admin/)

## Permissions 
This module provides two types of permissions:
* Administer Simple Google reCaptcha - allows user to access configuration page 
* Bypass Simple Google reCaptcha verification - allows to skip reCaptcha validation on forms 

To configure user permissions visit /admin/people/permissions

## Add reCaptcha validation to forms 
Currently there are two ways to add reCaptcha to forms: 
1. By adding form ID of any form at admin/config/services/simple_recaptcha
2. By adding reCaptcha handler to the webform - after enabling simple_recaptcha_webform module this can be simply added in webform handlers settings (/admin/structure/webform/manage/{webform}/handlers ) and doesn't require any extra configuration.

# How does it work 
Basically the module is adding custom markup and libraries needed to render and handle reCaptcha widget.
Once libraries are attached to the form, JS code will disable form submit buttons. 
When user tries to submit the form, following actions be executed, depending on state: 
1. Render reCaptcha widget - if it is first form submission 
2. get reCaptcha response - if reCaptcha widget is already rendered 
3. Add bit of error-like CSS - if reCaptcha widget is already rendered, but reCaptcha response is missing 
4. Send request to /api/simple_recaptcha/verify to validate reCaptcha response - if reCaptcha response is provided
5. Parse response from /api/simple_recaptcha/verify and unlock form submit buttons if user's response is verified 

Internal route /api/simple_recaptcha/verify is implemented because of two reasons:
* to avoid exposition of secret key in page markup
* to avoid problems with CORS policy 
