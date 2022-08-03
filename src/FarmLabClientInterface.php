<?php

namespace Drupal\farm_farmlab;

use GuzzleHttp\ClientInterface;

/**
 * Interface for FarmLab client.
 */
interface FarmLabClientInterface extends ClientInterface {

  /**
   * Helper function to get the authorized account.
   *
   * @return array|null
   *   The authorized account.
   */
  public function getAccount();

  /**
   * Helper function to get the connected farm.
   *
   * @return array|null
   *   The farm data.
   */
  public function getFarm();

  /**
   * Helper function to get boundaries associated with connected farm.
   *
   * @param array $params
   *   Request params.
   *
   * @return array
   *   Boundaries.
   */
  public function getBoundaries(array $params = []): array;

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
