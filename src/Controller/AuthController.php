<?php

namespace Drupal\farm_farmlab\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\TypedData\Type\DateTimeInterface;
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
   * The FarmLabClient.
   *
   * @var \Drupal\farm_farmlab\FarmLabClientInterface
   */
  protected $farmLabClient;

  /**
   * Constructs the BoundariesController.
   *
   * @param \Drupal\Core\TypedData\Type\DateTimeInterface $time
   *   The time service.
   * @param \Drupal\farm_farmlab\FarmLabClientInterface $farm_lab_client
   *   The FarmLabClient.
   */
  public function __construct(DateTimeInterface $time, FarmLabClientInterface $farm_lab_client) {
    $this->time = $time;
    $this->farmLabClient = $farm_lab_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('datetime.time'),
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
    $response = $this->farmLabClient->request('GET', 'Account');

    // Display message on failure.
    if ($response->getStatusCode() != 200) {

      $render['message'] = ['#markup' => $this->t('You must connect farmOS with your FarmLab account.')];
      $render['connect'] = Link::createFromRoute('Connect FarmLab', 'farm_farmlab.connect')->toRenderable();

      // Open in a modal.
      $render['connect']['#attributes']['class'][] = 'use-ajax';
      $render['connect']['#attributes']['data-dialog-type'] = 'modal';
      $render['connect']['#attributes']['data-dialog-options'] = '{"height": "auto", "width": "500px"}';

      // Render as a button.
      $render['connect']['#attributes']['class'][] = 'button';
      $render['connect']['#attributes']['class'][] = 'button--small';
    }

    // Else render a reset button.
    else {

      // Render reset button.
      $render['revoke'] = Link::createFromRoute('Reset FarmLab connection', 'farm_farmlab.revoke')->toRenderable();
      $render['revoke']['#weight'] = 100;
      $render['revoke']['#attributes']['class'][] = 'button';
      $render['revoke']['#attributes']['class'][] = 'button--danger';
    }

    // Render the account information in a text area.
    $account_body = Json::decode($response->getBody());
    $render['account'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Account'),
      '#value' => BaseJson::encode($account_body, JSON_PRETTY_PRINT),
      '#disabled' => TRUE,
      '#rows' => 8,
    ];

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
        'scope' => 'read search update owner',
        'redirect_uri' => $this->redirectUri(),
      ],
    ];
    $url = Url::fromUri($farmlab_settings->get('auth_url'), $options);
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

    // Clear any token the client has.
    $this->farmLabClient->setToken([]);

    // Complete the authorization flow.
    $params = [
      'code' => $code,
      'redirect_uri' => $this->redirectUri(),
    ];
    $response = $this->farmLabClient->request('GET', '/vasat/shepherd/auth', ['query' => $params]);
    $token_body = Json::decode($response->getBody());

    // Check for a valid token.
    $redirect = new RedirectResponse(Url::fromRoute('farm_farmlab.status')->toString());
    if (empty($token_body['payload'])) {
      $this->messenger()->addError($this->t('FarmLab connection failed. Please try again.'));
      return $redirect;
    }

    // Set expiration time.
    $token = $token_body['payload'];
    $now = $this->time->getCurrentTime();
    $expires_at = $now + $token['expires_in'] ?? 0;
    $token['expires_at'] = $expires_at;

    $this->state()->set('farm_farmlab.token', $token);
    $this->messenger()->addMessage($this->t('FarmLab connection successful.'));
    return $redirect;
  }

  /**
   * Route to revoke the FarmLab access.
   */
  public function revoke() {
    $this->state()->delete('farm_farmlab.token');
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

}
