<?php

namespace Drupal\farm_farmlab;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Client;

/**
 * FarmLab client.
 */
class FarmLabClient extends Client implements FarmLabClientInterface {

  /**
   * The FarmLab authorization URL.
   *
   * @var string
   */
  protected $authUrl;

  /**
   * The FarmLab OAuth token.
   *
   * @var array
   */
  protected $token;

  /**
   * FarmLab client constructor.
   *
   * @param string $api_url
   *   The FarmLab API base url.
   * @param string $auth_url
   *   The FarmLab authorization url.
   * @param array $config
   *   Guzzle client config.
   */
  public function __construct(string $api_url, string $auth_url, array $config = []) {
    $this->authUrl = trim($auth_url, '/');
    $this->token = [];
    $default_config = [
      'base_uri' => $api_url,
      'http_errors' => FALSE,
    ];
    $config = $default_config + $config;
    parent::__construct($config);
  }

  /**
   * {@inheritdoc}
   */
  public function requestAsync($method, $uri = '', array $options = []) {

    // Build an authorization header for each request refreshing the OAuth
    // token when necessary.
    $default_headers = $this->getAuthorizationHeader();
    $options['headers'] = $options['headers'] ? $options['headers'] + $default_headers : $default_headers;
    return parent::requestAsync($method, $uri, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function setToken(array $token) {
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public function grant(array $params): array {

    // Create a new Guzzle client.
    $client = new Client([
      'base_uri' => $this->authUrl,
      'http_errors' => FALSE,
    ]);
    $response = $client->request('POST', 'access/login', ['json' => $params]);
    $token_body = Json::decode($response->getBody());

    // Check for a valid token.
    if (empty($token_body['payload'])) {
      return [];
    }

    // Set expiration time.
    $token = $token_body['payload'];
    $now = \Drupal::time()->getCurrentTime();
    $expires_at = $now + $token['expires_in'] ?? 0;
    $token['expires_at'] = $expires_at;

    // Set the token for the client and return.
    $this->setToken($token);
    \Drupal::state()->set('farm_farmlab.token', $token);
    return $token;
  }

  /**
   * {@inheritdoc}
   */
  public function refreshToken(): array {

    // Bail if there is no refresh token.
    if (empty($this->token['refresh_token'])) {
      return [];
    }

    // Build refresh token params.
    $refresh_token = $this->token['refresh_token'];
    $farmlab_settings = \Drupal::config('farm_farmlab.settings');
    $params = [
      'grant_type' => 'refresh_token',
      'client_id' => $farmlab_settings->get('client_id'),
      'client_secret' => $farmlab_settings->get('client_secret'),
      'refresh_token' => $refresh_token,
    ];
    return $this->grant($params);
  }

  /**
   * Helper function to build the authorization header.
   *
   * @param bool $refresh
   *   Boolean indicating if a refresh can be performed.
   *
   * @return array
   *   An array with the Authorization header.
   */
  protected function getAuthorizationHeader(bool $refresh = TRUE): array {

    // Bail if there is no token.
    $header = [];
    if (empty($this->token)) {
      return $header;
    }

    // Refresh the token if expired.
    $now = \Drupal::time()->getCurrentTime();
    $expired = ($now + 120) > $this->token['expires_at'] ?? PHP_INT_MAX;
    if ($expired && $refresh) {
      $this->refreshToken();
      return $this->getAuthorizationHeader(FALSE);
    }

    // Return the access token in a Bearer Authorization header.
    $access_token = $this->token['access_token'];
    $header['Authorization'] = "Bearer $access_token";
    return $header;
  }

}
