<?php

namespace Drupal\farm_farmlab\Form;

use Drupal\asset\Entity\Asset;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\Core\Render\Element\Tableselect;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\farm_farmlab\FarmLabClientInterface;
use Drupal\farm_geo\Traits\WktTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * FarmLab boundaries.
 */
class FarmLabBoundariesForm extends FormBase {

  use WktTrait;

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
   * Constructs the BoundariesController.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\farm_farmlab\FarmLabClientInterface $farm_lab_client
   *   The FarmLabClient.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FarmLabClientInterface $farm_lab_client) {
    $this->entityTypeManager = $entity_type_manager;
    $this->farmLabClient = $farm_lab_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
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

    // Request all active boundaries for the farm.
    $boundaries = $this->farmLabClient->getBoundaries(['active' => 'true']);

    // Query assets with farmlab_ids.
    $asset_storage = $this->entityTypeManager->getStorage('asset');
    $farmlab_asset_mapping = [];
    if ($boundary_ids = array_column($boundaries, 'id')) {
      $matches = $asset_storage->getAggregateQuery()
        ->condition('id_tag.%delta.type', 'farmlab_id')
        ->condition('id_tag.%delta.id', $boundary_ids, 'IN')
        ->groupBy('id_tag.%delta.type')
        ->groupBy('id_tag.%delta.id')
        ->groupBy('id')
        ->execute();

      // Organize by farmlab_ids.
      foreach ($matches as $match) {
        if ($match['id_tag_type'] == 'farmlab_id') {
          $farmlab_asset_mapping[$match['id_tag_id']] = $match['id'];
        }
      }
    }
    $assets = $asset_storage->loadMultiple($farmlab_asset_mapping);

    // Table header.
    $table_header = [
      'name' => $this->t('Boundary name'),
      'type' => $this->t('Boundary type'),
      'asset' => $this->t('Land asset'),
    ];

    // Table.
    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => $table_header,
      '#options' => [],
      '#empty' => $this->t('No boundaries found in FarmLab. Click "Add boundary" to create existing land assets as FarmLab boundaries.'),
      '#js_select' => TRUE,
      '#process' => [
        [Tableselect::class, 'processTableselect'],
        [$this, 'processTableselect'],
      ],
    ];

    // Define boundary types.
    $boundary_types = [
      'paddock' => $this->t('Paddock'),
      'zones' => $this->t('Management Zones'),
      'field' => $this->t('Field'),
      'cadastral_boundary' => $this->t('Cadastral Boundary'),
      'carbon_estimation_area' => $this->t('Carbon Estimation Area'),
    ];

    // Add each boundary to the table.
    foreach ($boundaries as $boundary) {
      $boundary_id = $boundary['id'];
      $form['table']['#options'][$boundary_id] = [
        'asset' => '',
        'name' => $boundary['name'],
        'type' => $boundary_types[$boundary['boundaryType']] ?? $boundary['boundaryType'],
        '#attributes' => [
          'data-boundary-id' => $boundary['id'],
        ],
      ];

      // Add asset label and disable checkbox.
      if ($asset_id = $farmlab_asset_mapping[$boundary_id]) {
        $form['table']['#options'][$boundary_id]['asset'] = $assets[$asset_id]->toLink()->toString();
        $form['table']['#options'][$boundary_id]['#disabled'] = TRUE;
      }

    }

    // Render a map with the boundaries.
    $form['map'] = [
      '#type' => 'farm_map',
      '#weight' => -10,
      '#attached' => [
        'library' => ['farm_farmlab/boundaries-form'],
      ],
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import boundaries'),
    ];

    return $form;
  }

  /**
   * Process function to disable tableselect checkboxes.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   tableselect element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The processed element.
   */
  public static function processTableselect(array &$element, FormStateInterface $form_state, array &$complete_form) {

    // We have to process the #disabled property ourselves.
    // This can be removed when https://drupal.org/node/2101357 is fixed.
    foreach (Element::children($element['#options']) as $key) {
      $element[$key]['#disabled'] = $element['#options'][$key]['#disabled'] ?? FALSE;
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Define a mapping from farmlab boundary type to farmos land types.
    $boundary_type_mapping = [
      'paddock' => 'paddock',
      'zones' => 'other',
      'field' => 'field',
      'cadastral_boundary' => 'property',
      'carbon_estimation_area' => 'other',
    ];

    // Get selected boundary IDs.
    $boundary_ids = Checkboxes::getCheckedCheckboxes($form_state->getValue('table'));

    // Create an asset for each selected boundary.
    $boundaries = $this->farmLabClient->getBoundaries();
    foreach ($boundaries as $boundary) {
      if (!in_array($boundary['id'], $boundary_ids)) {
        continue;
      }

      // Create asset for each boundary.
      try {
        $geometry = \geoPHP::load(Json::encode($boundary['geojson']), 'geojson');
        $wkt = $geometry->out('wkt');
      }
      // Catch error if geophp doesn't parse the geometry.
      catch (\Exception $error) {
        $this->messenger()->addError($this->t('Boundary %boundaryName has invalid geometry: %error', ['%boundaryName' => $boundary['name'], '%error' => $error->getMessage()]));
        continue;
      }

      // Create land asset.
      $asset = Asset::create([
        'type' => 'land',
        'status' => 'active',
        'land_type' => $boundary_type_mapping[$boundary['boundaryType']] ?? 'other',
        'name' => $boundary['name'],
        'intrinsic_geometry' => $wkt,
        'is_fixed' => TRUE,
        'id_tag' => [
          ['type' => 'farmlab_id', 'id' => $boundary['id']],
        ],
      ]);
      $asset->save();
      $this->messenger()->addMessage(
        $this->t(
          'Created land asset <a href=":url">%label</a>',
          [
            ':url' => $asset->toUrl()->setAbsolute()->toString(),
            '%label' => $asset->label(),
          ],
        ),
      );
    }
  }

}
