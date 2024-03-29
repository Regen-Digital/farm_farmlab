<?php

namespace Drupal\farm_farmlab\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Url;
use Drupal\farm_farmlab\FarmLabClientInterface;
use Psy\Util\Json as BaseJson;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller for authorizing and connecting with FarmLab.
 */
class AuthController extends ControllerBase {

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\farm_farmlab\FarmLabClientInterface $farm_lab_client
   *   The FarmLabClient.
   */
  public function __construct(TimeInterface $time, EntityTypeManagerInterface $entity_type_manager, FarmLabClientInterface $farm_lab_client) {
    $this->time = $time;
    $this->entityTypeManager = $entity_type_manager;
    $this->farmLabClient = $farm_lab_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('datetime.time'),
      $container->get('entity_type.manager'),
      $container->get('farm_farmlab.farmlab_client'),
    );
  }

  /**
   * Authentication status page.
   *
   * @return array
   *   Render array.
   */
  public function status(): array {
    $render = [];

    // Get the authenticated account.
    $account = $this->farmLabClient->getAccount();

    // Display message on failure.
    if (empty($account)) {

      // Display message.
      $render['message'] = [
        '#type' => 'container',
        'message' => [
          '#markup' => $this->t('No FarmLab account connected. Connect a FarmLab account and farm to use FarmLab features.'),
        ],
      ];
      $connect = Link::createFromRoute('Connect FarmLab', 'farm_farmlab.connect')->toRenderable();

      // Open in a modal.
      $connect['#attributes']['class'][] = 'use-ajax';
      $connect['#attributes']['data-dialog-type'] = 'modal';
      $connect['#attributes']['data-dialog-options'] = '{"height": "auto", "width": "500px"}';

      // Render as a button.
      $connect['#attributes']['class'][] = 'button';
      $connect['#attributes']['class'][] = 'button--small';
      $render['connect'] = $connect;
      return $render;
    }

    // Else render a reset button.
    else {

      // Render reset button.
      $render['revoke'] = Link::createFromRoute('Reset FarmLab connection', 'farm_farmlab.revoke')->toRenderable();
      $render['revoke']['#weight'] = 100;
      $render['revoke']['#attributes']['class'][] = 'button';
      $render['revoke']['#attributes']['class'][] = 'button--danger';
    }

    // Start a debug area.
    $render['debug'] = [
      '#type' => 'details',
      '#title' => $this->t('Debug info'),
      '#open' => FALSE,
      '#weight' => 200,
    ];

    // Render list of account attributes.
    $account_keys = [
      'id' => $this->t('ID'),
      'name' => $this->t('Name'),
      'desc' => $this->t('Description'),
    ];
    $render['account'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Connected account'),
      '#items' => [],
    ];
    foreach ($account_keys as $key => $label) {
      if ($value = $account[$key]) {
        $render['account']['#items'][] = [
          '#markup' => "$label: $value",
        ];
      }
    }

    // Render the account information in a text area.
    $render['debug']['account'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Account'),
      '#value' => BaseJson::encode($account, JSON_PRETTY_PRINT),
      '#disabled' => TRUE,
      '#rows' => 8,
    ];

    // Display the connected farm.
    $render['farm'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#title' => $this->t('Connected farm'),
      '#items' => [],
    ];

    // If no farm_id is set, display link to select a farm.
    if (!$this->state()->get('farm_farmlab.farm_id')) {

      // Add message.
      $render['farm']['#items'][] = $this->t('No FarmLab farm connected. A FarmLab Farm must be connected to sync boundaries between applications.');

      // Check if this is the same user that authorized access.
      // If not, add a message requesting the original user to connect a farm.
      $user_id = $this->state()->get('farm_farmlab.user_id');
      if ($user_id != $this->currentUser()->id() && $user = $this->entityTypeManager->getStorage('user')->load($user_id)) {
        $render['farm']['#items'][] = $this->t('Please ask %user to finish connecting a FarmLab farm. Only the user that initially connected their FarmLab account can connect a farm.', ['%user' => $user->label()]);
      }
      // Else include a link to connect the farmlab farm.
      else {
        $render['select-farm'] = Link::createFromRoute($this->t('Connect FarmLab farm'), 'farm_farmlab.connect_farm')->toRenderable();
        $render['select-farm']['#weight'] = 50;
        $render['select-farm']['#attributes']['class'][] = 'button';
      }
    }
    // Else display selected farm info.
    elseif ($farm = $this->farmLabClient->getFarm()) {

      // Render list of farm attributes.
      $farm_keys = [
        'id' => $this->t('ID'),
        'name' => $this->t('Name'),
        'ownerName' => $this->t("Owner's name"),
        'ownerPhone' => $this->t("Owner's phone"),
        'ownerEmail' => $this->t("Owner's email"),
        'desc' => $this->t('Description'),
        'notes' => $this->t('Notes'),
      ];
      foreach ($farm_keys as $key => $label) {
        if ($value = $farm[$key]) {
          $render['farm']['#items'][] = [
            '#markup' => "$label: $value",
          ];
        }
      }

      // Render the farm information in a text area.
      $render['debug']['farm'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Farm'),
        '#value' => BaseJson::encode($farm, JSON_PRETTY_PRINT),
        '#disabled' => TRUE,
        '#rows' => 8,
      ];
    }

    return $render;
  }

  /**
   * Connect page.
   */
  public function connect(): array {

    // Render a description.
    $render['description'] = [
      '#type' => 'container',
      'text' => [
        '#markup' => $this->t('Click connect to login to FarmLab and authorize access for farmOS. Once authorized you will be redirected back to farmOS to complete the connection.'),
      ],
    ];

    // Build authorization link.
    $farmlab_settings = $this->config('farm_farmlab.settings');
    $options = [
      'query' => [
        'response_type' => 'code',
        'client_id' => $farmlab_settings->get('client_id'),
        'client_secret' => $farmlab_settings->get('client_secret'),
        'state' => $this->getAuthorizationState(),
        'scope' => 'read search write update owner app',
        'redirect_uri' => $this->redirectUri(),
      ],
    ];
    $auth_url = $farmlab_settings->get('auth_url');
    $auth_url = trim($auth_url, '/');
    $url = Url::fromUri("$auth_url/auth/grant", $options);
    $link = Link::fromTextAndUrl($this->t('Connect'), $url);

    // Render authorization link.
    $render['connect'] = $link->toRenderable();
    $render['connect']['#attributes'] = [
      'class' => ['button', 'button-action', 'button-primary', 'button-small'],
    ];

    return $render;
  }

  /**
   * Grant page to complete the authorization flow.
   */
  public function grant(Request $request) {
    $render = [];

    $code = $request->get('code');
    if (empty($code)) {
      $render['error'] = [
        '#markup' => 'No code provided. Please connect again.',
      ];
      return $render;
    }

    // Complete the authorization flow.
    $farmlab_settings = $this->config('farm_farmlab.settings');
    $params = [
      'grant_type' => 'authorization_code',
      'client_id' => $farmlab_settings->get('client_id'),
      'client_secret' => $farmlab_settings->get('client_secret'),
      'state' => $this->getAuthorizationState(),
      'code' => $code,
      'redirect_uri' => $this->redirectUri(),
    ];
    $token = $this->farmLabClient->grant($params);
    $this->clearAuthorizationState();

    // Check for a valid token.
    if (empty($token)) {
      $this->messenger()->addError($this->t('FarmLab connection failed. Could not retrieve token. Please try again.'));
      return new RedirectResponse(Url::fromRoute('farm_farmlab.status')->toString());
    }

    // Connect the account.
    // The FarmLab server takes a second to propagate the token to other
    // API servers and services.
    sleep(2);
    $account = NULL;
    $response = $this->farmLabClient->request('GET', 'Account');

    // Check for failure and retry.
    if ($response->getStatusCode() != 200) {
      $response = $this->farmLabClient->request('GET', 'Account');
    }

    // Check for failure and log message.
    if ($response->getStatusCode() != 200) {
      $response_body = Json::decode($response->getBody());
      $error = BaseJson::encode($response_body, JSON_PRETTY_PRINT);
      $this->getLogger('farm_farmlab')->error("Could not retrieve FarmLab account: $error");
    }
    else {
      // Return the first account.
      $response_body = Json::decode($response->getBody());
      if (isset($response_body['payload']) && count($response_body['payload'])) {
        $account = reset($response_body['payload']);
      }
    }

    // Save the farmOS user and FarmLab account to state.
    if (!empty($account)) {
      $this->state()->set('farm_farmlab.user_id', $this->currentUser()->id());
      $this->state()->set('farm_farmlab.account_id', $account['id']);
    }
    else {
      $this->messenger()->addError($this->t('FarmLab connection failed. Could not connect account. Please try again.'));
      return new RedirectResponse(Url::fromRoute('farm_farmlab.status')->toString());
    }

    return new RedirectResponse(Url::fromRoute('farm_farmlab.status')->toString());
  }

  /**
   * Route to revoke the FarmLab access.
   */
  public function revoke() {
    $this->state()->delete('farm_farmlab.token');
    $this->state()->delete('farm_farmlab.account_id');
    $this->state()->delete('farm_farmlab.farm_id');
    $this->state()->delete('farm_farmlab.user_id');
    $this->messenger()->addMessage($this->t('The FarmLab authentication was reset.'));
    return new RedirectResponse(Url::fromRoute('farm_farmlab.status')->toString());
  }

  /**
   * Helper function that returns the OAuth redirect URI.
   *
   * @return string
   *   The redirect URI.
   */
  protected function redirectUri(): string {
    return Url::fromRoute('farm_farmlab.grant')->setAbsolute(TRUE)->toString();
  }

  /**
   * Helper function that returns current authorization state.
   *
   * @return string
   *   The authorization state.
   */
  protected function getAuthorizationState(): string {
    $state = $this->state()->get('farm_farmlab.authorization_state');
    if (empty($state)) {
      $state = bin2hex(random_bytes(16));
      $this->state()->set('farm_farmlab.authorization_state', $state);
    }
    return $state;
  }

  /**
   * Helper function to clear the authorization state.
   */
  protected function clearAuthorizationState() {
    $this->state()->delete('farm_farmlab.authorization_state');
  }

}
