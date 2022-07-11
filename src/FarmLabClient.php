<?php

namespace Drupal\farm_farmlab;

use GuzzleHttp\Client;

/**
 * FarmLab client.
 */
class FarmLabClient extends Client implements FarmLabClientInterface {

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
   * @param array $config
   *   Guzzle client config.
   */
  public function __construct(string $api_url, array $config = []) {
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
  public function refreshToken() {
    // @todo Implement refresh logic.
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
    $expired = ($now + 60) > $this->token['expires_at'] ?? PHP_INT_MAX;
    if ($expired && $refresh) {
      $this->setToken([]);
      $this->refreshToken();
      return $this->getAuthorizationHeader(FALSE);
    }

    // Return the access token in a Bearer Authorization header.
    $access_token = $this->token['access_token'];
    $header['Authorization'] = "Bearer $access_token";
    return $header;
  }

}
