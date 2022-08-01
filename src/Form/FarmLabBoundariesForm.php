<?php

namespace Drupal\farm_farmlab\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\farm_farmlab\FarmLabClientInterface;
use Drupal\farm_geo\Traits\WktTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * FarmLab boundaries.
 */
class FarmLabBoundariesForm extends FormBase {

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
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_farmlab_boundaries_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Request all active boundaries.
    $params = ['active' => 'true'];
    $response = $this->farmLabClient->request('GET', 'Paddock', ['query' => $params]);
    $boundaries_body = Json::decode($response->getBody());

    // Table header.
    $table_header = [
      'name' => $this->t('Name'),
      'type' => $this->t('Type'),
    ];

    // Table.
    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => $table_header,
      '#options' => [],
      '#empty' => $this->t('No boundaries found in FarmLab. Click "Add boundary" to create existing land assets as FarmLab boundaries.'),
      '#js_select' => FALSE,
    ];

    $boundary_types = [
      'paddock' => $this->t('Paddock'),
      'zones' => $this->t('Management Zones'),
      'field' => $this->t('Field'),
      'cadastral_boundary' => $this->t('Cadastral Boundary'),
      'carbon_estimation_area' => $this->t('Carbon Estimation Area'),
    ];

    // Add each boundary to the table.
    foreach ($boundaries_body["payload"] ?? [] as $boundary) {
      $form['table']['#options'][$boundary['id']] = [
        'name' => $boundary['name'],
        'type' => $boundary_types[$boundary['boundaryType']] ?? $boundary['boundaryType'],
        '#attributes' => [
          'data-boundary-id' => $boundary['id'],
        ],
      ];
    }

    // Render a map with the boundaries.
    $form['map'] = [
      '#type' => 'farm_map',
      '#weight' => -10,
      '#attached' => [
        'library' => ['farm_farmlab/boundaries-form'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @todo Implement submitForm() method.
  }

}
