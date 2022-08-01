<?php

namespace Drupal\farm_farmlab;

use GuzzleHttp\ClientInterface;

/**
 * Interface for FarmLab client.
 */
interface FarmLabClientInterface extends ClientInterface {

  /**
   * Helper function to set the FarmLab OAuth token.
   *
   * @param array $token
   *   The OAuth token array.
   */
  public function setToken(array $token);

  /**
   * Helper function to perform OAuth grants.
   *
   * @param array $params
   *   Params for the OAuth grant.
   *
   * @return array
   *   The new token.
   */
  public function grant(array $params): array;

  /**
   * Helper function to refresh the FarmLab OAuth token.
   *
   * @return array
   *   The new token.
   */
  public function refreshToken(): array;

}
