<?php

namespace Drupal\farm_farmlab\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\farm_farmlab\FarmLabClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Relays geojson data from FarmLab API.
 */
class GeojsonController extends ControllerBase {

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
   * Boundaries geojson.
   */
  public function boundaries() {

    // Build geojson response.
    $geojson = [
      'type' => 'FeatureCollection',
      'features' => [],
    ];

    // Parse each boundary into a feature.
    $boundaries = $this->farmLabClient->getBoundaries(['active' => 'true']);
    foreach ($boundaries as $boundary) {

      // Get the boundary geojson.
      $feature = $boundary['geojson'];
      unset($boundary['geojson']);

      // Set the feature id.
      $feature['id'] = $boundary['id'];

      // Set the feature properties.
      $feature['properties'] = $boundary;

      // Add the feature.
      $geojson['features'][] = $feature;
    }

    return JsonResponse::create($geojson);
  }

  /**
   * Cadastral geojson.
   */
  public function cadastral(Request $request) {

    // Get coordinates from request query params.
    $coordinates = $request->get('coordinates');
    $points = array_map(function (string $coord) {
      return explode(',', $coord);
    }, $coordinates);

    // Fetch cadastrals.
    $payload = [
      "type" => "Polygon",
      "coordinates" => [$points],
    ];
    $response = $this->farmLabClient->request('POST', '/vasat/harvest/searchBounds', ['json' => $payload]);

    // Return on error.
    if ($response->getStatusCode() != 200) {
      return $response;
    }

    // Build geojson response.
    $geojson = [
      'type' => 'FeatureCollection',
      'features' => [],
    ];

    // Process each result.
    $bounds_body = Json::decode($response->getBody());
    foreach ($bounds_body['payload']['payload']['results'] as $result) {

      // Get id, properties and geom.
      $id = $result['id'];
      $properties = $result['item'];
      $geom = $properties['geom'];
      unset($properties['geom']);

      // Add the feature.
      $geojson['features'][] = [
        'type' => 'Feature',
        'id' => $id,
        'geometry' => $geom,
        'properties' => $properties,
      ];
    }

    return JsonResponse::create($geojson);
  }

}
