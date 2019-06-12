<?php

namespace Drupal\simple_recaptcha\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableJsonResponse;
use Drupal\Core\Cache\CacheableMetadata;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Custom API endpoint, used by module JS to communicate with reCaptcha API.
 */
class SimpleRecaptchaController extends ControllerBase {

  /**
   * Verifies recaptcha response.
   */
  public function verifyResponse(Request $request) {
    $client = \Drupal::httpClient();
    $recaptcha_site_key = $request->query->get('recaptcha_site_key');
    // Deny empty requests.
    if (!$recaptcha_site_key) {
      throw new AccessDeniedHttpException();
    }

    // Deny requests with invalid site key provided.
    if ($recaptcha_site_key != \Drupal::config('simple_recaptcha.config')->get('site_key')) {
      throw new AccessDeniedHttpException();
    }
    $recaptcha_response = $query = $request->query->get('recaptcha_response');
    $params = [
      'secret' => \Drupal::config('simple_recaptcha.config')->get('secret_key'),
      'response' => $recaptcha_response,
    ];
    // Sending POST Request with $json_data to example.com.
    $request = $client->post('https://www.google.com/recaptcha/api/siteverify', [
      'form_params' => $params,
    ]);
    // Getting Response after JSON Decode.
    $api_response = Json::decode($request->getBody()->getContents());

    if (!$api_response['success']) {
      \Drupal::logger('simple_recaptcha')->notice(t('reCaptcha validation failed, error codes: @errors', ['@errors' => implode(',', $api_response['error-codes'])]));
    }

    $data['#cache'] = [
      'max-age' => 3600,
      'contexts' => [
        'user.roles',
        'url.query_args:recaptcha_response',
        'url.query_args:recaptcha_site_key',
      ],
    ];
    $response = new CacheableJsonResponse($api_response);
    $response->addCacheableDependency(CacheableMetadata::createFromRenderArray($data));
    return new $response();
  }

}
