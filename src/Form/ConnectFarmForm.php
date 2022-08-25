<?php

namespace Drupal\farm_farmlab\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\farm_farmlab\FarmLabClientInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for selecting which FarmLab Farm to associate with the farmOS instance.
 */
class ConnectFarmForm extends FormBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The FarmLabClient.
   *
   * @var \Drupal\farm_farmlab\FarmLabClientInterface
   */
  protected $farmLabClient;

  /**
   * Constructs a CreateEstimateForm object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\farm_farmlab\FarmLabClientInterface $farm_lab_client
   *   The FarmLab client.
   */
  public function __construct(StateInterface $state, FarmLabClientInterface $farm_lab_client) {
    $this->farmLabClient = $farm_lab_client;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('farm_farmlab.farmlab_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'farm_farmlab_connect_farm';
  }

  /**
   * Access check for the form.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {

    // Check for permission.
    $permission = AccessResult::allowedIfHasPermission($account, 'connect farmlab');

    // Ensure an account has been connected.
    $account_id = $this->state->get('farm_farmlab.account_id');
    $account_connected = AccessResult::allowedIf(!empty($account_id));
    $permission = $permission->andIf($account_connected);

    // Ensure no farm_id is already connected.
    $farm_id = $this->state->get('farm_farmlab.farm_id');
    $farm_not_connected = AccessResult::allowedIf(empty($farm_id));
    return $permission->andIf($farm_not_connected);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    // Farm select options.
    $form['option'] = [
      '#type' => 'radios',
      '#title' => $this->t('How to connect?'),
      '#options' => [
        'new' => $this->t('Create new'),
        'existing' => $this->t('Connect existing'),
      ],
      '#default_value' => 'new',
      '#attributes' => [
        'name' => 'option',
      ],
    ];

    // Get the list of available farms.
    // @todo Only query active farms.
    $response = $this->farmLabClient->request('GET', 'Farm');

    // Display message on failure.
    if ($response->getStatusCode() != 200) {
      $this->getLogger('farm_farmlab')->error($response->getBody());
      $this->messenger()->addError($this->t('FarmLab connection failed. Failed to request farms.'));
      return $form;
    }

    // Get the farm data.
    $farm_count = 0;
    $farm_body = Json::decode($response->getBody());
    if (empty($farm_body['payload'])) {
      $this->messenger()->addWarning($this->t('No existing farms found. Please create a new farm.'));
    }
    else {
      $farm_count = count($farm_body['payload']);
    }

    // Require a new farm if there are no existing farms.
    $existing_farm_options = [];
    if ($farm_count == 0) {
      $form['option']['#default_value'] = 'new';
      unset($form['option']['#options']['existing']);
    }
    // Default to the single option.
    elseif ($farm_count == 1) {
      $farm = reset($farm_body['payload']);
      $form['option']['#options'][$farm['id']] = $farm['name'];
      $form['option']['#default_value'] = $farm['id'];
      unset($form['option']['#options']['existing']);
    }
    // Display multiple existing options.
    elseif ($farm_count > 0) {

      // Default to existing.
      $form['option']['#default_value'] = 'existing';

      // Existing farm options.
      foreach ($farm_body['payload'] as $farm) {
        $existing_farm_options[$farm['id']] = $farm['name'];
      }
    }

    // New farm option.
    $form['new'] = [
      '#tree' => TRUE,
      '#type' => 'container',
      '#title' => $this->t('Create new farm'),
      '#states' => [
        'visible' => [
          ':input[name="option"]' => ['value' => 'new'],
        ],
      ],
    ];
    $form['new']['farm_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Farm name'),
      '#description' => $this->t('The name of the new FarmLab farm.'),
    ];
    $form['new']['owner_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Owner's name"),
    ];
    $form['new']['owner_email'] = [
      '#type' => 'email',
      '#title' => $this->t("Owner's email"),
    ];
    $form['new']['owner_phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Owner's phone"),
    ];
    $form['new']['notes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Notes'),
    ];

    // Existing field.
    $form['existing_farm'] = [
      '#type' => 'select',
      '#title' => $this->t('Existing farm options'),
      '#options' => $existing_farm_options,
      '#states' => [
        'visible' => [
          ':input[name="option"]' => ['value' => 'existing'],
        ],
      ],
    ];

    // Submit button.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Get the submitted option.
    $option = $form_state->getValue('option');

    // Easy single option.
    $farm_id = NULL;
    if (is_numeric($option)) {
      $farm_id = (int) $option;
    }
    // Else use one of many existing.
    elseif ($option == 'existing' && $selected = $form_state->getValue('existing_farm')) {
      $farm_id = (int) $selected;
    }
    elseif ($option == 'new') {
      $account_id = $this->state->get('farm_farmlab.account_id');

      // Build new farm payload.
      $new = $form_state->getValue('new');
      $payload = [
        'account' => ['id' => $account_id],
        'type' => 'Farm',
        'dormant' => TRUE,
        'name' => $new['farm_name'],
        'ownerEmail' => $new['owner_email'],
        'ownerName' => $new['owner_name'],
        'ownerPhone' => $new['owner_phone'],
      ];

      // Create new farm.
      $response = $this->farmLabClient->request('POST', 'Farm', [RequestOptions::JSON => $payload]);
      if ($response->getStatusCode() != 200) {
        $body = (string) $response->getBody();
        $this->getLogger('farm_farmlab')->error($body);
        $this->messenger()->addError($this->t('Failed to create FarmLab Farm: %message', ['%message' => $body]));
      }
      else {
        $response_body = Json::decode($response->getBody());
        $farm_id = (int) $response_body['payload']['id'];
      }
    }

    // Set the final farm_id to state.
    if (is_int($farm_id)) {
      $this->state->set('farm_farmlab.farm_id', $farm_id);
      $this->messenger()->addMessage($this->t('FarmLab connection successful.'));
      $form_state->setRedirect('farm_farmlab.status');
    }
    // Else try again.
    else {
      $this->messenger()->addError($this->t('Failed to connect FarmLab Farm. Please try again.'));
      $form_state->setRedirect('farm_farmlab.select_farm');
    }
  }

}
