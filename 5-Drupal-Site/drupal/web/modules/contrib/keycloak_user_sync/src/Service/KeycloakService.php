<?php

namespace Drupal\keycloak_user_sync\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\user\Entity\User;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Service for synchronizing users between Drupal and Keycloak.
 *
 * @package Drupal\keycloak_user_sync\Service
 */
class KeycloakService {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  private $httpClient;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private $config;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * The profile service.
   *
   * @var \Drupal\keycloak_user_sync\Service\ProfileService
   */
  private $profileService;

  /**
   * The Drupal settings service.
   *
   * @var \Drupal\Core\Site\Settings
   */
  protected $settings;

  /**
   * The cached authentication token.
   *
   * @var string|null
   */
  private $token;

  /**
   * The operation type ('insert' or 'update').
   *
   * @var string
   */
  private $operation = 'insert';

  /**
   * Constructs a new KeycloakService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\keycloak_user_sync\Service\ProfileService $profile_service
   *   The profile service.
   * @param \Drupal\Core\Site\Settings $settings
   *   The Drupal settings service.
   */
  public function __construct(
    ClientInterface $http_client,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    ProfileService $profile_service,
    Settings $settings,
  ) {
    $this->httpClient = $http_client;
    $this->config = $config_factory->get('keycloak_user_sync.settings');
    $this->logger = $logger_factory->get('keycloak_user_sync');
    $this->profileService = $profile_service;
    $this->settings = $settings;
    $this->token = NULL;
  }

  /**
   * Gets credentials from settings.php or settings.local.php.
   *
   * @return array
   *   Array containing credentials.
   */
  private function getCredentials(): array {
    $credentials = $this->settings->get('keycloak_user_sync.credentials', []);

    if (empty($credentials)) {
      $this->logger->warning('Keycloak credentials not found in settings.php or settings.local.php');
    }

    // Ensure we have the required client credentials.
    if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
      $this->logger->error('Missing required Keycloak client credentials (client_id or client_secret)');
    }

    return $credentials;
  }

  /**
   * Gets connection settings from settings.php or settings.local.php.
   *
   * @return array
   *   Array containing connection settings.
   */
  private function getConnectionSettings(): array {
    $connection = $this->settings->get('keycloak_user_sync.connection', []);

    if (empty($connection['url']) || empty($connection['realm'])) {
      $this->logger->error('Missing required Keycloak connection settings in settings.php');
    }

    return $connection;
  }

  /**
   * Validates the required Keycloak configuration.
   *
   * @return bool
   *   TRUE if all required settings are present, FALSE otherwise.
   */
  private function validateConfig(): bool {
    $connection = $this->getConnectionSettings();
    $credentials = $this->getCredentials();

    $required_connection = ['url', 'realm'];
    $required_credentials = ['client_id', 'client_secret'];

    foreach ($required_connection as $setting) {
      if (empty($connection[$setting])) {
        $this->logger->error('Missing required Keycloak connection setting: @setting. Check your settings.php or settings.local.php file.',
          ['@setting' => $setting]
        );
        return FALSE;
      }
    }

    foreach ($required_credentials as $credential) {
      if (empty($credentials[$credential])) {
        $this->logger->error('Missing required Keycloak credential: @credential. Check your settings.php or settings.local.php file.',
          ['@credential' => $credential]
        );
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Makes an authenticated request to Keycloak.
   *
   * @param string $method
   *   The HTTP method (GET, POST, PUT, DELETE).
   * @param string $endpoint
   *   The endpoint path after the base URL.
   * @param array $options
   *   Additional request options.
   *
   * @return array|null
   *   The response data or null on failure.
   */
  private function authenticatedRequest(string $method, string $endpoint, array $options = []): ?array {
    if (!$this->validateConfig()) {
      return NULL;
    }

    if (!$this->token) {
      $this->token = $this->getKeycloakToken();
      if (!$this->token) {
        return NULL;
      }
    }

    $connection = $this->getConnectionSettings();
    $url = "{$connection['url']}/auth{$endpoint}";

    // Merge authorization headers with provided options.
    $options['headers'] = array_merge(
      $options['headers'] ?? [],
      ['Authorization' => "Bearer {$this->token}"]
    );

    try {
      $response = $this->httpClient->request($method, $url, $options);
      $statusCode = $response->getStatusCode();
      
      // Handle different status codes appropriately
      if ($statusCode === 204) {
        return [];
      }
      elseif ($statusCode >= 200 && $statusCode < 300) {
        return json_decode($response->getBody()->getContents(), TRUE);
      }
      else {
        $this->logger->error('Keycloak request returned unexpected status code @status for @method @url', [
          '@status' => $statusCode,
          '@method' => $method,
          '@url' => $url,
        ]);
        return NULL;
      }
    }
    catch (RequestException $e) {
      $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'unknown';
      $responseBody = '';
      
      if ($e->hasResponse()) {
        $responseBody = $e->getResponse()->getBody()->getContents();
      }
      
      $this->logger->error('Keycloak request failed: @error (Status: @status, Method: @method, URL: @url, Response: @response)', [
        '@error' => $e->getMessage(),
        '@status' => $statusCode,
        '@method' => $method,
        '@url' => $url,
        '@response' => $responseBody ? substr($responseBody, 0, 500) : 'No response body',
      ]);
      return NULL;
    }
  }

  /**
   * Gets the Keycloak access token.
   *
   * @return string|null
   *   The Keycloak access token or NULL on failure.
   */
  private function getKeycloakToken(): ?string {
    $credentials = $this->getCredentials();
    $connection = $this->getConnectionSettings();

    if (!$this->validateConfig()) {
      return NULL;
    }

    $url = "{$connection['url']}/auth/realms/{$connection['realm']}/protocol/openid-connect/token";

    try {
      $response = $this->httpClient->request('POST', $url, [
        'form_params' => [
          'grant_type' => 'client_credentials',
          'client_id' => $credentials['client_id'],
          'client_secret' => $credentials['client_secret'],
        ],
      ]);
      $data = json_decode($response->getBody()->getContents(), TRUE);
      return $data['access_token'] ?? NULL;
    }
    catch (RequestException $e) {
      $this->logger->error('Keycloak token request failed: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Safely gets user profile data.
   *
   * @param \Drupal\user\Entity\User $account
   *   The user account.
   * @param string $operation
   *   The operation being performed ('insert' or 'update').
   *
   * @return array
   *   Array of user data formatted for Keycloak.
   */
  private function getSafeUserData(User $account, string $operation = 'insert'): array {
    $userData = [
      'username' => $account->getAccountName(),
      'enabled' => TRUE,
      'attributes' => [],
    ];

    // Get operation-specific settings.
    $settings = $this->config->get($operation . '_settings') ?: [];

    // Set email verification status.
    $userData['requiredActions'] = array_values($this->config->get($operation . '_required_actions')) ?? [];
    $userData['emailVerified'] = $this->config->get($operation . '_email_verified') ?? TRUE;

    // Set required actions if any are configured.
    if (!empty($settings['required_actions'])) {
      $userData['requiredActions'] = array_values(array_filter($settings['required_actions']));
    }

    // Handle update-specific settings.
    if ($operation === 'update') {
      if (empty($this->config->get('update_existing_fields'))) {
        // Even with minimal updates, we should include the email to avoid 400 errors
        $userData['email'] = $account->getEmail();
        return $userData;
      }
    }

    // Check if email should be updated (configurable for update operations)
    $updateEmail = $operation === 'insert' || $this->config->get('update_email_field') !== FALSE;
    
    // Get field mappings.
    $fieldMappings = $this->config->get('field_mappings') ?? [];

    foreach ($fieldMappings as $mapping) {
      $keycloakField = $mapping['keycloak_field'];
      $source = $mapping['source'];
      $drupalField = $mapping['drupal_field'];

      // For update operations, only skip email mapping if explicitly disabled
      if ($operation === 'update' && $keycloakField === 'email' && !$updateEmail) {
        continue;
      }

      try {
        $value = $this->profileService->getFieldValue($account, $source, $drupalField);

        // Convert field value to string safely.
        $safeValue = '';

        if ($value !== NULL) {
          if (is_object($value) && method_exists($value, 'getValue')) {
            $fieldValue = $value->getValue();
            $safeValue = $fieldValue['value'] ?? '';
          }
          elseif (is_array($value)) {
            if (isset($value['value'])) {
              $safeValue = (string) $value['value'];
            }
            else {
              $array = (array) $value;
              $firstValue = reset($array);
              if (is_array($firstValue) && isset($firstValue['value'])) {
                $safeValue = (string) $firstValue['value'];
              }
              elseif (is_scalar($firstValue)) {
                $safeValue = (string) $firstValue;
              }
            }
          }
          elseif (is_scalar($value)) {
            $safeValue = (string) $value;
          }
        }

        // Handle standard Keycloak fields.
        if (in_array($keycloakField, ['firstName', 'lastName', 'email'])) {
          $userData[$keycloakField] = $safeValue;
        }
        // Handle custom fields by adding them to attributes.
        else {
          $userData['attributes'][$keycloakField] = [$safeValue];
        }

        $this->logger->debug('Mapped @source:@drupal_field to @keycloak_field with value: @value', [
          '@source' => $source,
          '@drupal_field' => $drupalField,
          '@keycloak_field' => $keycloakField,
          '@value' => $safeValue,
        ]);
      }
      catch (\Exception $e) {
        $this->logger->error('Error getting field value for @keycloak_field: @error', [
          '@keycloak_field' => $keycloakField,
          '@error' => $e->getMessage()
        ]);
      }
    }

    // Always ensure email is set if available from the Drupal account
    // This prevents 400 errors from Keycloak when email is missing
    if ($account->getEmail() && (!isset($userData['email']) || empty($userData['email']))) {
      $userData['email'] = $account->getEmail();
    }

    // For insert operations, also set username to email if username is empty
    if ($operation === 'insert' && empty($userData['username']) && $account->getEmail()) {
      $userData['username'] = $account->getEmail();
    }

    // Validate required fields are present
    if (empty($userData['username'])) {
      throw new \InvalidArgumentException('Username cannot be empty for Keycloak user.');
    }

    $this->logger->debug('Final user data for Keycloak (@operation): @data', [
      '@operation' => $operation,
      '@data' => json_encode($userData),
    ]);

    return $userData;
  }

  /**
   * Creates a new user in Keycloak.
   *
   * @param \Drupal\user\Entity\User $account
   *   The user account to create in Keycloak.
   *
   * @throws \Exception
   *   If the user creation fails.
   */
  public function createUser(User $account): void {
    $connection = $this->getConnectionSettings();

    if (!$account->getAccountName()) {
      throw new \InvalidArgumentException('User account must have a username.');
    }

    // First check if user already exists.
    $search_response = $this->authenticatedRequest(
      'GET',
      "/admin/realms/{$connection['realm']}/users",
      ['query' => ['username' => $account->getAccountName()]]
    );

    if (!empty($search_response)) {
      $this->logger->warning('User @name already exists in Keycloak.', [
        '@name' => $account->getAccountName(),
      ]);
      return;
    }

    try {
      // Get user data including attributes for insert operation.
      $userData = $this->getSafeUserData($account, 'insert');

      // Validate that email is present
      if (empty($userData['email']) && $account->getEmail()) {
        $userData['email'] = $account->getEmail();
      }

      $jsonData = json_encode($userData);
      if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \Exception('Failed to encode user data as JSON: ' . json_last_error_msg());
      }

      $this->logger->debug('Attempting to create Keycloak user @username with data: @data', [
        '@username' => $account->getAccountName(),
        '@data' => $jsonData,
      ]);

      $response = $this->authenticatedRequest(
        'POST',
        "/admin/realms/{$connection['realm']}/users",
        [
          'headers' => ['Content-Type' => 'application/json'],
          'json' => $userData,
        ]
      );

      if ($response !== NULL) {
        $this->logger->notice('User @name created in Keycloak successfully', [
          '@name' => $account->getAccountName(),
        ]);
      }
      else {
        $this->logger->error('Create request returned NULL response for user @name', [
          '@name' => $account->getAccountName(),
        ]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to create user @name in Keycloak: @error Data: @data', [
        '@name' => $account->getAccountName(),
        '@error' => $e->getMessage(),
        '@data' => isset($jsonData) ? $jsonData : 'N/A',
      ]);
      throw $e;
    }
  }

  /**
   * Updates an existing user in Keycloak.
   *
   * @param \Drupal\user\Entity\User $account
   *   The user account to update in Keycloak.
   *
   * @throws \Exception
   */
  public function updateUser(User $account): void {
    $connection = $this->getConnectionSettings();

    if (!$account->getAccountName()) {
      throw new \InvalidArgumentException('User account must have a username.');
    }

    $search_response = $this->authenticatedRequest(
      'GET',
      "/admin/realms/{$connection['realm']}/users",
      ['query' => ['username' => $account->getAccountName()]]
    );

    if (empty($search_response)) {
      $this->logger->warning('No Keycloak user found with username: @name.', [
        '@name' => $account->getAccountName(),
      ]);
      return;
    }

    $keycloak_user_id = $search_response[0]['id'];

    try {
      // Get user data including attributes for update operation.
      $userData = $this->getSafeUserData($account, 'update');

      // Validate that email is present to prevent 400 errors
      if (empty($userData['email'])) {
        $this->logger->warning('Email is missing from user data for update operation. Adding account email.');
        $userData['email'] = $account->getEmail();
      }

      // Ensure the data is properly formatted for JSON.
      $jsonData = json_encode($userData);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->logger->error('JSON encoding error: @error', [
          '@error' => json_last_error_msg(),
        ]);
        throw new \Exception('Failed to encode user data as JSON: ' . json_last_error_msg());
      }

      $this->logger->debug('Attempting to update Keycloak user @username (ID: @id) with JSON data: @data', [
        '@username' => $account->getAccountName(),
        '@id' => $keycloak_user_id,
        '@data' => $jsonData,
      ]);

      $response = $this->authenticatedRequest(
        'PUT',
        "/admin/realms/{$connection['realm']}/users/$keycloak_user_id",
        [
          'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
          ],
          'json' => $userData,
        ]
      );

      if ($response !== NULL) {
        $this->logger->notice('User @name (ID: @id) updated in Keycloak successfully', [
          '@name' => $account->getAccountName(),
          '@id' => $keycloak_user_id,
        ]);
      }
      else {
        $this->logger->error('Update request returned NULL response for user @name', [
          '@name' => $account->getAccountName(),
        ]);
      }
    }
    catch (\Exception $e) {
      $this->logger->error('Failed to update user @name in Keycloak: @error Data: @data', [
        '@name' => $account->getAccountName(),
        '@error' => $e->getMessage(),
        '@data' => isset($jsonData) ? $jsonData : 'N/A',
      ]);
      throw $e;
    }
  }

  /**
   * Deletes a user from Keycloak.
   *
   * @param \Drupal\user\Entity\User $account
   *   The user account to delete from Keycloak.
   */
  public function deleteUser(User $account): void {
    $connection = $this->getConnectionSettings();

    $search_response = $this->authenticatedRequest(
      'GET',
      "/admin/realms/{$connection['realm']}/users",
      ['query' => ['username' => $account->getAccountName()]]
    );

    if (empty($search_response)) {
      $this->logger->warning('No Keycloak user found with username: @name.', [
        '@name' => $account->getAccountName(),
      ]);
      return;
    }

    $keycloak_user_id = $search_response[0]['id'];

    $response = $this->authenticatedRequest(
      'DELETE',
      "/admin/realms/{$connection['realm']}/users/$keycloak_user_id",
      ['headers' => ['Content-Type' => 'application/json']]
    );

    if ($response !== NULL) {
      $this->logger->notice('User @name deleted from Keycloak.', [
        '@name' => $account->getAccountName(),
      ]);
    }
  }

  /**
   * Tests the connection to Keycloak.
   *
   * @return bool
   *   TRUE if connection is successful, FALSE otherwise.
   */
  public function testConnection(): bool {
    return $this->getKeycloakToken() !== NULL;
  }

  /**
   * Validates the module configuration and field mappings.
   *
   * @return array
   *   Array of validation errors, empty if no errors.
   */
  public function validateConfiguration(): array {
    $errors = [];

    // Check basic configuration
    if (!$this->validateConfig()) {
      $errors[] = 'Basic Keycloak configuration is invalid or missing.';
    }

    // Check field mappings
    $fieldMappings = $this->config->get('field_mappings') ?? [];
    $keycloakFields = [];
    
    foreach ($fieldMappings as $index => $mapping) {
      if (empty($mapping['keycloak_field'])) {
        $errors[] = "Field mapping at index $index is missing keycloak_field.";
        continue;
      }
      
      if (empty($mapping['source']) || empty($mapping['drupal_field'])) {
        $errors[] = "Field mapping for '{$mapping['keycloak_field']}' is missing source or drupal_field.";
      }
      
      if (in_array($mapping['keycloak_field'], $keycloakFields)) {
        $errors[] = "Duplicate keycloak_field '{$mapping['keycloak_field']}' found in field mappings.";
      }
      
      $keycloakFields[] = $mapping['keycloak_field'];
    }

    return $errors;
  }

  /**
   * Gets the current module configuration summary.
   *
   * @return array
   *   Configuration summary.
   */
  public function getConfigurationSummary(): array {
    $connection = $this->getConnectionSettings();
    $credentials = $this->getCredentials();
    $fieldMappings = $this->config->get('field_mappings') ?? [];

    return [
      'connection_configured' => !empty($connection['url']) && !empty($connection['realm']),
      'credentials_configured' => !empty($credentials['client_id']) && !empty($credentials['client_secret']),
      'field_mappings_count' => count($fieldMappings),
      'update_existing_fields' => (bool) $this->config->get('update_existing_fields'),
      'update_email_field' => $this->config->get('update_email_field') !== FALSE,
      'connection_test' => $this->testConnection(),
      'validation_errors' => $this->validateConfiguration(),
    ];
  }

}
