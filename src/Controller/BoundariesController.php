<?php

namespace Drupal\farm_farmlab\Controller;

use Drupal\Component\Serialization\Json;
use Psy\Util\Json as BaseJson;
use Drupal\Core\Controller\ControllerBase;
use Drupal\farm_farmlab\FarmLabClientInterface;
use Drupal\farm_geo\Traits\WktTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * FarmLab boundaries.
 */
class BoundariesController extends ControllerBase {

  use WktTrait;

  /**
   * The FarmLabClient.
   *
   * @var \Drupal\farm_farmlab\FarmLabClientInterface
   */
  protected $farmLabClient;

  /**
   * Constructs the BoundariesController.
   *
   * @param \Drupal\farm_farmlab\FarmLabClientInterface $farm_lab_client
   *   The FarmLabClient.
   */
  public function __construct(FarmLabClientInterface $farm_lab_client) {
    $this->farmLabClient = $farm_lab_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('farm_farmlab.farmlab_client'),
    );
  }

  /**
   * Boundaries page.
   */
  public function boundaries() {

    // Request all active boundaries.
    $params = ['active' => 'true'];
    $response = $this->farmLabClient->request('GET', 'Paddock', ['query' => $params]);
    $boundaries_body = Json::decode($response->getBody());

    // Build list of boundaries.
    $boundaries = [];
    foreach ($boundaries_body["payload"] ?? [] as $boundary) {

      // Catch geos error:
      // Exception: Points of LinearRing do not form a closed linestring in
      // GEOSWKBReader->readHEX()
      try {
        $geom = \geoPHP::load(Json::encode($boundary['geojson']), 'json');
        $reduced = \geoPHP::geometryReduce($geom);
        $wkt = $reduced->out('wkt');
      }
      catch (\Exception $exception) {
        continue;
      }
      $boundaries[] = [
        'name' => $boundary['name'],
        'type' => $boundary['boundaryType'],
        'id' => $boundary['id'],
        'geom' => $wkt,
      ];
    }

    // Combine all geometries into one.
    $geoms = array_column($boundaries, 'geom');
    $final_wkt = $this->combineWkt($geoms);

    // Render a map with the boundaries.
    $render['map'] = [
      '#type' => 'farm_map',
      '#map_settings' => [
        'wkt' => $final_wkt,
      ],
      '#weight' => -10,
    ];

    // Boundaries textarea.
    $render['boundaries'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Boundaries'),
      '#value' => BaseJson::encode($boundaries, JSON_PRETTY_PRINT),
      '#disabled' => TRUE,
      '#rows' => 15,
    ];

    return $render;
  }

}
