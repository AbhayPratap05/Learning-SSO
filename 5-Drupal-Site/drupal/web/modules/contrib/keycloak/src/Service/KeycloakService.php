<?php

namespace Drupal\keycloak\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\openid_connect\Plugin\OpenIDConnectClientManager;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Keycloak service.
 */
class KeycloakService implements KeycloakServiceInterface {

  /**
   * Machine name for the Keycloak openid_connect plugin.
   */
  protected const OPENID_CONNECT_CONFIG_NAME = 'openid_connect.client.keycloak';

  /**
   * The config factory interface.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * A configuration object containing Keycloak client settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  /**
   * Client plugin manager of the OpenID Connect module.
   *
   * @var \Drupal\openid_connect\Plugin\OpenIDConnectClientManager
   */
  protected OpenIDConnectClientManager $oidcClientManager;

  /**
   * A language manager instance.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The users' private tempstore instance.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $privateTempstore;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;


  /**
   * Default keys to be stored to / retrieved from a Keycloak user session.
   *
   * @var array
   */
  private static $sessionInfoKeys;

  /**
   * Constructor for Drupal\keycloak\Service\KeycloakService.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\openid_connect\Plugin\OpenIDConnectClientManager $oidc_client_manager
   *   Client plugin manager of the OpenID Connect module.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   A language manager instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Account proxy for the currently logged-in user.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $private_tempstore
   *   A private tempstore factory instance.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   A logger channel factory instance.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    OpenIDConnectClientManager $oidc_client_manager,
    LanguageManagerInterface $language_manager,
    AccountProxyInterface $current_user,
    PrivateTempStoreFactory $private_tempstore,
    LoggerChannelFactoryInterface $logger
  ) {
    $this->config = $config_factory->get(static::OPENID_CONNECT_CONFIG_NAME);
    $this->configFactory = $config_factory;
    $this->oidcClientManager = $oidc_client_manager;
    $this->languageManager = $language_manager;
    $this->currentUser = $current_user;
    $this->privateTempstore = $private_tempstore;
    $this->loggerFactory = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return $this->config->get('enabled');
  }

  /**
   * {@inheritdoc}
   */
  public function getLocale() {
    return $this->config->get('settings.keycloak_locale_param');
  }

  /**
   * {@inheritdoc}
   */
  public function isKeycloakUser() {
    // Whether the user is not authenticated or the Keycloak client disabled.
    if (!$this->currentUser->isAuthenticated()) {
      return FALSE;
    }

    // If the user was logged in using Keycloak, we will find session
    // information in the users' private tempstore.
    $tempstore = $this->privateTempstore->get('keycloak');
    return !empty($tempstore);
  }

  /**
   * {@inheritdoc}
   */
  public function getSessionInfoDefaultKeys() {
    if (!isset(self::$sessionInfoKeys)) {
      $default_keys = [
        self::KEYCLOAK_SESSION_ACCESS_TOKEN,
        self::KEYCLOAK_SESSION_REFRESH_TOKEN,
        self::KEYCLOAK_SESSION_ID_TOKEN,
        self::KEYCLOAK_SESSION_CLIENT_ID,
        self::KEYCLOAK_SESSION_SESSION_ID,
      ];

      self::$sessionInfoKeys = $default_keys;
    }

    return self::$sessionInfoKeys;
  }

  /**
   * {@inheritdoc}
   */
  public function getSessionInfo($keys = NULL) {
    $session_info = [];

    if (!$this->isKeycloakUser()) {
      return $session_info;
    }

    $default_keys = $this->getSessionInfoDefaultKeys();

    $keys = empty($keys) ? $default_keys : array_intersect($default_keys, $keys);
    $tempstore = $this->privateTempstore->get('keycloak');

    foreach ($keys as $key) {
      $session_info[$key] = $tempstore->get($key);
    }

    return $session_info;
  }

  /**
   * {@inheritdoc}
   */
  public function setSessionInfo(array $info) {
    // Whether the user is not authenticated or the Keycloak client disabled.
    if (!$this->currentUser->isAuthenticated() || !$this->isEnabled()) {
      return FALSE;
    }

    $default_keys = $this->getSessionInfoDefaultKeys();
    $old_values = $this->getSessionInfo();
    $new_values = array_merge($old_values, $info);

    $tempstore = $this->privateTempstore->get('keycloak');
    foreach ($default_keys as $key) {
      $tempstore->set($key, $new_values[$key]);
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isI18nEnabled() {
    return $this->isEnabled() &&
      $this->languageManager->isMultilingual() &&
      $this->config->get('settings.keycloak_i18n.enabled');
  }

  /**
   * {@inheritdoc}
   */
  public function getI18nMapping($reverse = FALSE, $include_enabled = TRUE) {
    $mappings = [];

    $languages = $this->languageManager->getLanguages();
    if (empty($languages)) {
      return $mappings;
    }

    $configured = $this->config->get('settings.keycloak_i18n_mapping');
    // The stored mapping is an unkeyed list of associative arrays
    // with 'langcode' and 'target' as keys. Transform it to an assoc
    // array of 'langcode' => 'target'.
    $kc_mappings = [];
    if (!empty($configured)) {
      foreach ($configured as $mapping) {
        $kc_mappings[$mapping['langcode']] = $mapping['target'];
      }
    }

    // Create the i18n locale mapping information.
    foreach ($languages as $langcode => $language) {
      if (empty($kc_mappings[$langcode]) && !$include_enabled) {
        continue;
      }

      $mapping = [
        'language_id' => $langcode,
        'locale' => !empty($kc_mappings[$langcode]) ? $kc_mappings[$langcode] : $langcode,
        'label' => $language->getName(),
      ];

      $mappings[$reverse ? $mapping['locale'] : $langcode] = $mapping;
    }

    return $mappings;
  }

  /**
   * {@inheritdoc}
   */
  public function isSsoEnabled() {
    return $this->isEnabled() &&
      $this->config->get('settings.keycloak_sso');
  }

  /**
   * {@inheritdoc}
   */
  public function isKeycloakSignOutEnabled() {
    return $this->config->get('enabled') &&
      $this->config->get('settings.keycloak_sign_out');
  }

  /**
   * {@inheritdoc}
   */
  public function getKeycloakSignOutEndpoint() {
    return $this->getEndpoints()['end_session'];
  }

  /**
   * {@inheritdoc}
   */
  public function getKeycloakSignoutResponse(array $session_information) {
    $logout_redirect = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();

    if (
      $this->isKeycloakSignOutEnabled() &&
      !empty($session_information[self::KEYCLOAK_SESSION_ID_TOKEN])
    ) {
      // We do an internal redirect here and modify it in
      // our KeycloakRequestSubscriber.
      return new RedirectResponse(Url::fromRoute('keycloak.logout', [], [
        'query' => [
          'id_token_hint' => $session_information[self::KEYCLOAK_SESSION_ID_TOKEN],
          'post_logout_redirect_uri' => $logout_redirect,
        ],
      ])->toString());
    }

    return new RedirectResponse($logout_redirect);
  }

  /**
   * {@inheritdoc}
   */
  public function isCheckSessionEnabled() {
    return $this->config->get('enabled') &&
      $this->config->get('settings.check_session.enabled');
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckSessionInterval() {
    return $this->config->get('settings.check_session.interval');
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckSessionIframeUrl() {
    return $this->getEndpoints()['session_iframe'];
  }

  /**
   * {@inheritdoc}
   */
  public function getClientInstance() {
    $config = $this->config->get('settings');

    if (empty($config)) {
      $config = [];
    }

    return $this->oidcClientManager->createInstance(
      'keycloak',
      $config
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLogger() {
    return $this->loggerFactory->get('openid_connect_keycloak');
  }

  /**
   * {@inheritdoc}
   */
  public function isDebugMode() {
    return $this->config->get('settings.debug');
  }

}
