<?php

namespace Drupal\farm_farmlab;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;

/**
 * Factory service for FarmLabClient.
 */
class FarmLabClientFactory {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructor for the FarmLabClientFactory.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state) {
    $this->configFactory = $config_factory;
    $this->state = $state;
  }

  /**
   * Returns a FarmLabClient.
   *
   * @return \Drupal\farm_farmlab\FarmLabClient
   *   The FarmLabClient.
   */
  public function getApiClient() {

    // Get config options.
    $config = $this->configFactory->get('farm_farmlab.settings');

    // Create client and set the current token.
    $client = new FarmLabClient($config->get('api_url'));
    $client->setToken($this->state->get('farm_farmlab.token') ?? []);

    return $client;
  }

}
