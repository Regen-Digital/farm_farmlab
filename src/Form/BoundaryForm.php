<?php

namespace Drupal\farm_farmlab\Form;

use Drupal\asset\Entity\Asset;
use Drupal\asset\Entity\AssetInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\farm_farmlab\FarmLabClientInterface;
use Drupal\farm_geo\Traits\WktTrait;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating boundaries in FarmLab.
 */
class BoundaryForm extends FormBase {

  use WktTrait;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The FarmLabClient.
   *
   * @var \Drupal\farm_farmlab\FarmLabClientInterface
   */
  protected $farmLabClient;

  /**
   * The connected farm ID.
   *
   * @var int|null
   */
  protected $farmId;

  /**
   * Constructs a CreateEstimateForm object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\farm_farmlab\FarmLabClientInterface $farm_lab_client
   *   The FarmLab client.
   */
  public function __construct(StateInterface $state, EntityTypeManagerInterface $entity_type_manager, FarmLabClientInterface $farm_lab_client) {
    $this->entityTypeManager = $entity_type_manager;
    $this->farmLabClient = $farm_lab_client;
    $this->state = $state;
    $this->farmId = $this->state->get('farm_farmlab.farm_id');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('entity_type.manager'),
      $container->get('farm_farmlab.farmlab_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_farmlab_boundary_add';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Ensure connected to farm lab.
    if (empty($this->farmId)) {
      $form['error']['#markup'] = $this->t('Not connected to FarmLab.');
      return $form;
    }

    // Fieldset for asset selection.
    $form['asset_selection'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Project areas'),
    ];

    $form['asset_selection']['description'] = [
      '#markup' => $this->t('Choose farmOS land assets you would like to create as FarmLab boundaries.'),
    ];

    // Let the user choose assets individually or in bulk.
    $form['asset_selection']['bulk'] = [
      '#type' => 'radios',
      '#options' => [
        1 => $this->t('Bulk by land type'),
        0 => $this->t('Individual land assets'),
      ],
      '#default_value' => 1,
      '#ajax' => [
        'wrapper' => 'asset-selection-wrapper',
        'callback' => [$this, 'assetSelectionCallback'],
      ],
    ];

    // AJAX Wrapper for the asset selection.
    $form['asset_selection']['asset_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'asset-selection-wrapper',
      ],
    ];

    // Simple entity autocomplete for individual asset selection.
    $bulk_select = (boolean) $form_state->getValue('bulk', 1);
    if (!$bulk_select) {
      $form['asset_selection']['asset_wrapper']['asset'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Land asset'),
        '#description' => $this->t('Search for land assets by their name. Use commas to select multiple land assets.'),
        '#target_type' => 'asset',
        '#selection_handler' => 'default',
        '#selection_settings' => [
          'target_bundles' => ['land'],
        ],
        '#tags' => TRUE,
        '#required' => TRUE,
      ];
    }
    // Else bulk select by land type.
    else {
      $land_type_options = farm_land_type_options();
      $form['asset_selection']['asset_wrapper']['land_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Land type'),
        '#options' => $land_type_options,
        '#required' => TRUE,
        '#ajax' => [
          'wrapper' => 'asset-selection-wrapper',
          'callback' => [$this, 'assetSelectionCallback'],
        ],
      ];

      // Display asset options.
      if ($land_type = $form_state->getValue('land_type')) {
        $asset_storage = $this->entityTypeManager->getStorage('asset');
        $asset_ids = $asset_storage->getQuery()
          ->accessCheck()
          ->condition('status', 'active')
          ->condition('land_type', $land_type)
          ->condition('is_location', TRUE)
          ->condition('intrinsic_geometry', NULL, 'IS NOT NULL')
          ->execute();
        $assets = $asset_storage->loadMultiple($asset_ids);
        $asset_options = array_map(function (AssetInterface $asset) {
          return $asset->label();
        }, $assets);

        // Display checkboxes for each asset.
        $form_state->setValue('asset_bulk', []);
        $form['asset_selection']['asset_wrapper']['asset_bulk'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Select assets'),
          '#options' => $asset_options,
          '#default_value' => array_keys($asset_options),
          '#required' => TRUE,
        ];

        // Display message is there are no options.
        if (empty($asset_options)) {
          $form['asset_selection']['asset_wrapper']['asset_bulk'] = [
            '#markup' => $this->t('No @land_type land assets found. Make sure these land assets are not archived and have a geometry.', ['@land_type' => $land_type_options[$land_type]]),
          ];
        }
      }
    }

    $form['boundary_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Boundary type'),
      '#options' => [
        'paddock' => $this->t('Paddock'),
        'zones' => $this->t('Management Zones'),
        'field' => $this->t('Field'),
        'cadastral_boundary' => $this->t('Cadastral Boundary'),
        'carbon_estimation_area' => $this->t('Carbon Estimation Area'),
      ],
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add to FarmLab'),
    ];

    return $form;
  }

  /**
   * AJAX callback for the asset selection container.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The asset selection container.
   */
  public function assetSelectionCallback(array $form, FormStateInterface $form_state) {
    return $form['asset_selection']['asset_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Bail if no farm is connected.
    if (empty($this->farmId)) {
      return;
    }

    // Get the submitted assets.
    $bulk = (boolean) $form_state->getValue('bulk');
    $asset_ids = $bulk
      ? Checkboxes::getCheckedCheckboxes($form_state->getValue('asset_bulk', []))
      : array_column($form_state->getValue('asset', []), 'target_id');
    $assets = Asset::loadMultiple($asset_ids);
    if (empty($assets)) {
      $this->messenger()->addError($this->t('No assets selected.'));
      return;
    }

    // Create each asset in FarmLab.
    $boundary_type = $form_state->getValue('boundary_type');
    foreach ($assets as $asset) {

      // Build payload.
      $payload = [
        'farm' => ['id' => $this->farmId],
        'name' => $asset->label(),
        // @todo Use correct FarmLab statuses.
        'status' => $asset->get('status')->value == 'active' ? 'scratched' : 'archived',
        'type' => 'Paddock',
        'boundaryType' => $boundary_type,
      ];

      // Convert each geometry to WKT.
      $wkt = $asset->get('geometry')->value;
      $geometry = \geoPHP::load($wkt, 'wkt');
      $geojson = Json::decode($geometry->out('json'));
      $payload['geojson'] = [
        'type' => 'Feature',
        'properties' => [],
        'geometry' => $geojson,
      ];
      $this->farmLabClient->request('POST', 'Paddock', ['json' => $payload]);
    }

    // Redirect to boundaries page.
    $form_state->setRedirect('farm_farmlab.boundaries');
  }

}
