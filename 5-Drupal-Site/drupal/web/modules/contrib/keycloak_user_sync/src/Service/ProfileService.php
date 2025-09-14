<?php

namespace Drupal\keycloak_user_sync\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\user\Entity\User;

/**
 * Service for handling user profiles.
 */
class ProfileService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructs a new ProfileService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->config = $config_factory->get('keycloak_user_sync.settings');
    $this->logger = $logger_factory->get('keycloak_user_sync');
  }

  /**
   * Gets a field value from a user's profile or account.
   *
   * @param \Drupal\user\Entity\User $account
   *   The user account.
   * @param string $source
   *   The source of the field value.
   * @param string $field_name
   *   The field name.
   *
   * @return mixed
   *   The field value or null if not found.
   *
   * @throws \Exception
   */
  public function getFieldValue(User $account, string $source, string $field_name) {
    $value = NULL;

    try {
      if ($source === 'user') {
        $value = $this->getUserAccountFieldValue($account, $field_name);
      }
      elseif (strpos($source, 'profile:') === 0) {
        $profile_type = substr($source, 8);
        $value = $this->getProfileFieldValue($account, $profile_type, $field_name);
      }

      $this->logger->debug('Raw field value for @field: @value', [
        '@field' => $field_name,
        '@value' => print_r($value, TRUE),
      ]);

      return $value;
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting field value: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Gets a field value from user account.
   */
  private function getUserAccountFieldValue(User $account, string $field_name) {
    if ($field_name === 'mail') {
      return $account->getEmail();
    }

    if ($field_name === 'name') {
      return $account->getAccountName();
    }

    if ($account->hasField($field_name)) {
      $field = $account->get($field_name);
      if (!$field->isEmpty()) {
        return $field->first()->getValue();
      }
    }

    return NULL;
  }

  /**
   * Gets a field value from a specific profile type.
   */
  private function getProfileFieldValue(User $account, string $profile_type, string $field_name) {
    try {
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      $profiles = $profile_storage->loadByProperties([
        'uid' => $account->id(),
        'type' => $profile_type,
      ]);

      if (empty($profiles)) {
        $this->logger->warning('No @type profile found for user @name', [
          '@type' => $profile_type,
          '@name' => $account->getAccountName(),
        ]);
        return NULL;
      }

      $profile = reset($profiles);
      if ($profile->hasField($field_name)) {
        $field = $profile->get($field_name);
        if (!$field->isEmpty()) {
          return $field->first()->getValue();
        }
      }

      return NULL;
    }
    catch (\Exception $e) {
      $this->logger->error('Error getting profile field value: @error', ['@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Tries to load a user profile.
   *
   * @param \Drupal\user\Entity\User $account
   *   The user account.
   *
   * @return false|mixed|null
   *   The user profile or NULL if not found.
   */
  public function getUserProfile(User $account): mixed {
    try {
      $profile_storage = $this->entityTypeManager->getStorage('profile');
      $profiles = $profile_storage->loadByProperties([
        'uid' => $account->id(),
        'type' => $this->config->get('sync_profile_type'),
      ]);

      return !empty($profiles) ? reset($profiles) : NULL;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

}
