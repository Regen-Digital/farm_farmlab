<?php

namespace Drupal\farm_farmlab;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;

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
   * The connected account ID.
   *
   * @var int|null
   */
  protected $accountId;

  /**
   * The connected farm ID.
   *
   * @var int|null
   */
  protected $farmId;

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
    $this->accountId = $config['account_id'] ?? NULL;
    $this->farmId = $config['farm_id'] ?? NULL;
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
  public function requestAsync(string $method, $uri = '', array $options = []): PromiseInterface {

    // Build an authorization header for each request refreshing the OAuth
    // token when necessary.
    $default_headers = $this->getAuthorizationHeader();
    $headers = $options['headers'] ?? [];
    $options['headers'] = $headers + $default_headers;
    return parent::requestAsync($method, $uri, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccount() {

    // Bail if no account ID.
    if (empty($this->accountId)) {
      return NULL;
    }

    // Get the authenticated account.
    $response = $this->request('GET', "Account/$this->accountId");

    // Return empty on failure.
    if ($response->getStatusCode() != 200) {
      return NULL;
    }

    // Return the account.
    $response_body = Json::decode($response->getBody());
    return $response_body['payload'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFarm() {

    // Bail if no farm ID.
    if (empty($this->farmId)) {
      return NULL;
    }

    // Get the connected farm by ID.
    $response = $this->request('GET', "Farm/$this->farmId");

    // Return empty on failure.
    if ($response->getStatusCode() != 200) {
      return NULL;
    }

    // Return the farm.
    $response_body = Json::decode($response->getBody());
    return $response_body['payload'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getBoundaries(array $params = []): array {

    // Limit to connected farm.
    if ($this->farmId) {
      $params += ['farm' => $this->farmId];
    }
    $response = $this->request('GET', 'Paddock', ['query' => $params]);

    // Return empty on failure.
    if ($response->getStatusCode() != 200) {
      return [];
    }

    // Return the boundaries.
    $response_body = Json::decode($response->getBody());
    return $response_body['payload'] ?? [];
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
