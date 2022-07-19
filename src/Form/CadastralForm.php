<?php

namespace Drupal\farm_farmlab\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\farm_geo\Traits\WktTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for creating land assets from cadastrals.
 */
class CadastralForm extends FormBase {

  use WktTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CreateEstimateForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_farmlab_cadastral_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Render map.
    $form['map'] = [
      '#type' => 'farm_map',
      '#weight' => -5,
      '#attached' => [
        'library' => ['farm_farmlab/cadastral-form'],
      ],
    ];

    // Load cadastrals button.
    $load = [
      '#type' => 'button',
      '#value' => $this->t('Load cadastrals'),
      '#attributes' => [
        'id' => 'load-cadastrals',
        'style' => ['float: right;'],
      ],
    ];

    // Table caption.
    $table_caption = [
      '#type' => 'div',
      'name' => [
        '#markup' => $this->t('Selected cadastrals'),
        '#attributes' => [
          'style' => ['float: left;'],
        ],
      ],
      'load' => $load,
    ];

    // Table header.
    $table_header = [
      $this->t('Selected'),
      $this->t('Name'),
      $this->t('Lot number'),
      $this->t('Plan number'),
    ];

    // Table.
    $form['table'] = [
      '#type' => 'table',
      '#caption' => $table_caption,
      '#header' => $table_header,
      '#rows' => [],
      '#attributes' => [
        'id' => 'selected-cadastrals',
      ],
    ];

    // Add a placeholder row to the empty table.
    $form['table']['#rows'][] = [
      'class' => 'placeholder',
      'data' => [
        [
          'data' => $this->t('Zoom the map to the area of interest and load cadastrals. Then select the cadastrals to import as land assets.'),
          'colspan' => 4,
        ],
      ],
    ];

    // Submit button.
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Cadastrals'),
      '#disabled' => TRUE,
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
