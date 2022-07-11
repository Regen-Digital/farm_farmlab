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
   * Helper function to refresh the FarmLab OAuth token.
   */
  public function refreshToken();

}
