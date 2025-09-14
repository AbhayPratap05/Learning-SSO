<?php

namespace Drupal\keycloak_user_sync\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\keycloak_user_sync\Service\KeycloakService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Keycloak user sync settings.
 */
class KeycloakUserSyncSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Keycloak service.
   *
   * @var \Drupal\keycloak_user_sync\Service\KeycloakService
   */
  protected $keycloakService;

  /**
   * Constructs a KeycloakUserSyncSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory service.
   * @param \Drupal\keycloak_user_sync\Service\KeycloakService $keycloak_service
   *   The Keycloak service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    KeycloakService $keycloak_service,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->entityTypeManager = $entity_type_manager;
    $this->setLoggerFactory($logger_factory);
    $this->keycloakService = $keycloak_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('keycloak_user_sync.keycloak_service')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['keycloak_user_sync.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'keycloak_user_sync_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('keycloak_user_sync.settings');

    $form['settings_info'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Keycloak connection settings must be configured in settings.php or settings.local.php. Example: <pre>@example</pre>See modules README.md for more instructions on how to configure the needed service user in Keycloak.', [
        '@example' => "\$settings['keycloak_user_sync.connection'] = [\n  'url' => 'https://keycloak.example.com',\n  'realm' => 'your-realm',\n];\n\n\$settings['keycloak_user_sync.credentials'] = [\n  'client_id' => 'your_client_id',\n  'client_secret' => 'your_client_secret',\n];"
      ]),
      '#prefix' => '<div class="messages messages--info">',
      '#suffix' => '</div>',
    ];

    $form['operation_settings'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('User Operation Settings'),
    ];

    $form['insert_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('User Creation Settings'),
      '#group' => 'operation_settings',
      '#tree' => TRUE,
    ];

    $form['update_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('User Update Settings'),
      '#group' => 'operation_settings',
      '#tree' => TRUE,
    ];

    $insert_settings = $this->buildOperationSettings('insert');
    $update_settings = $this->buildOperationSettings('update');

    $form['insert_settings'] += $insert_settings;
    $form['update_settings'] += $update_settings;

    $form['sync_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Synchronization Settings'),
      '#open' => TRUE,
      '#prefix' => '<div id="field-mappings-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['sync_settings']['field_mappings_info'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Map Keycloak fields to Drupal fields. For each mapping, select the source (user account or specific profile type) and the corresponding field.'),
      '#prefix' => '<div class="description">',
      '#suffix' => '</div><br>',
    ];

    // Get available profile types.
    $profile_types = $this->getAvailableProfileTypes();

    $source_options = [
      'user' => $this->t('User Account'),
    ];
    foreach ($profile_types as $type => $label) {
      $source_options['profile:' . $type] = $this->t('Profile: @label', ['@label' => $label]);
    }

    // Get mappings from form state or config.
    if (!$form_state->has('field_mappings')) {
      $mappings = $config->get('field_mappings');
      if (!is_array($mappings) || empty($mappings)) {
        $mappings = [
          [
            'keycloak_field' => 'email',
            'source' => 'user',
            'drupal_field' => 'mail',
          ],
        ];
      }
      $form_state->set('field_mappings', $mappings);
    }

    $current_mappings = $form_state->get('field_mappings');

    $form['sync_settings']['field_mappings'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Keycloak Field'),
        $this->t('Source'),
        $this->t('Drupal Field'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No field mappings configured.'),
    ];

    foreach ($current_mappings as $delta => $mapping) {
      $form['sync_settings']['field_mappings'][$delta] = [
        'keycloak_field' => [
          '#type' => 'textfield',
          '#title' => $this->t('Keycloak field'),
          '#title_display' => 'invisible',
          '#default_value' => $mapping['keycloak_field'] ?? '',
          '#size' => 30,
          '#required' => FALSE,
        ],
        'source' => [
          '#type' => 'select',
          '#title' => $this->t('Source'),
          '#title_display' => 'invisible',
          '#options' => $source_options,
          '#default_value' => $mapping['source'] ?? 'user',
          '#required' => FALSE,
        ],
        'drupal_field' => [
          '#type' => 'textfield',
          '#title' => $this->t('Drupal field'),
          '#title_display' => 'invisible',
          '#default_value' => $mapping['drupal_field'] ?? '',
          '#size' => 30,
          '#required' => FALSE,
        ],
        'remove' => [
          '#type' => 'submit',
          '#value' => $this->t('Remove'),
          '#name' => "remove_$delta",
          '#submit' => ['::removeFieldMapping'],
          '#ajax' => [
            'callback' => '::updateFieldMappings',
            'wrapper' => 'field-mappings-wrapper',
          ],
          '#attributes' => ['class' => ['button--small', 'button--danger']],
          '#row_index' => $delta,
        ],
      ];
    }

    $form['sync_settings']['add_mapping'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add Field Mapping'),
      '#submit' => ['::addFieldMapping'],
      '#ajax' => [
        'callback' => '::updateFieldMappings',
        'wrapper' => 'field-mappings-wrapper',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Builds the settings form elements for user operations.
   *
   * @param string $operation
   *   The operation type ('insert' or 'update').
   *
   * @return array
   *   Form elements for the operation.
   */
  protected function buildOperationSettings($operation) {
    $config = $this->config('keycloak_user_sync.settings');
    $settings = [];

    $settings['email_verified'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Set users as email verified'),
      '#default_value' => $config->get($operation . '_email_verified') ?? TRUE,
      '#description' => $this->t('If checked, @operation users will be set with verified email status.',
        ['@operation' => $operation === 'insert' ? 'new' : 'updated']),
    ];

    $settings['required_actions'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Required Actions'),
      '#options' => [
        'VERIFY_EMAIL' => $this->t('Verify Email'),
        'UPDATE_PASSWORD' => $this->t('Update Password'),
        'CONFIGURE_TOTP' => $this->t('Configure TOTP'),
        'UPDATE_PROFILE' => $this->t('Update Profile'),
        'VERIFY_PROFILE' => $this->t('Verify Profile'),
        'UPDATE_USER_LOCALE' => $this->t('Update User Locale'),
      ],
      '#default_value' => $config->get($operation . '_required_actions') ?? [],
      '#description' => $this->t('Select which actions users must complete upon @operation.',
        ['@operation' => $operation === 'insert' ? 'first login' : 'next login after update']),
    ];

    if ($operation === 'insert') {
      $settings['hint'] = [
        '#type' => 'markup',
        '#markup' => $this->t('Note: If you want to sync additional fields upon user creation, enable the "Update Keycloak fields with mapped Drupal fields" feature in the "User Update Settings" vertical tab.'),
        '#prefix' => '<div class="messages messages--status">',
        '#suffix' => '</div>',
      ];
    }

    if ($operation === 'update') {
      $settings['update_existing_fields'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Update Keycloak fields with mapped Drupal fields'),
        '#default_value' => $config->get('update_existing_fields') ?? FALSE,
        '#description' => $this->t('If checked, any user create or user update call will also update the existing field values in Keycloak according to the field mapping below.'),
      ];

      $settings['update_email_field'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Update email field in Keycloak'),
        '#default_value' => $config->get('update_email_field') !== FALSE,
        '#description' => $this->t('If checked, the email field will be included in update requests to Keycloak. Unchecking this will prevent email updates but may cause 400 errors if Keycloak requires the email field. It is recommended to keep this enabled.'),
      ];
    }

    return $settings;
  }

  /**
   * Gets available profile types.
   *
   * @return array
   *   Array of profile types keyed by machine name with label as value.
   */
  private function getAvailableProfileTypes(): array {
    try {
      $types = [];
      $profile_types = $this->entityTypeManager->getStorage('profile_type')
        ->loadMultiple();
      foreach ($profile_types as $type) {
        $types[$type->id()] = $type->label();
      }
      return $types;
    }
    catch (\Exception $e) {
      $this->getLogger('keycloak_user_sync')->error('Failed to load profile types: @error', ['@error' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Ajax callback to update field mappings table.
   */
  public function updateFieldMappings(array &$form, FormStateInterface $form_state) {
    return $form['sync_settings'];
  }

  /**
   * Submit handler for adding a new field mapping.
   */
  public function addFieldMapping(array &$form, FormStateInterface $form_state) {
    $current_mappings = $form_state->get('field_mappings');
    $current_mappings[] = [
      'keycloak_field' => '',
      'source' => 'user',
      'drupal_field' => '',
    ];
    $form_state->set('field_mappings', $current_mappings);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for testing the Keycloak connection.
   */
  public function testConnection(array &$form, FormStateInterface $form_state) {
    try {
      $keycloak_service = $this->keycloakService;
      $token = $keycloak_service->testConnection();

      if ($token) {
        $this->messenger()
          ->addStatus($this->t('Successfully connected to Keycloak.'));
      }
      else {
        $this->messenger()
          ->addError($this->t('Failed to connect to Keycloak. Please check your connection settings and credentials in settings.local.php.'));
      }
    }
    catch (\Exception $e) {
      $this->messenger()
        ->addError($this->t('Error testing connection: @error', ['@error' => $e->getMessage()]));
    }
  }

  /**
   * Submit handler for removing a field mapping.
   */
  public function removeFieldMapping(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $button_name = $trigger['#name'];
    $parts = explode('_', $button_name);
    $delta = end($parts);

    $mappings = $form_state->get('field_mappings');
    if (isset($mappings[$delta])) {
      unset($mappings[$delta]);
    }

    if (empty($mappings)) {
      $mappings = [
        [
          'keycloak_field' => 'email',
          'source' => 'user',
          'drupal_field' => 'mail',
        ],
      ];
    }

    $form_state->set('field_mappings', $mappings);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $values = $form_state->getValue('field_mappings');
    if ($values) {
      $keycloak_fields = [];
      $drupal_fields = [];
      foreach ($values as $delta => $row) {

        if (empty($row['keycloak_field']) && empty($row['drupal_field'])) {
          continue;
        }

        // If one field is filled, both must be filled.
        if (empty($row['keycloak_field']) || empty($row['drupal_field'])) {
          $form_state->setError($form['sync_settings']['field_mappings'][$delta],
            $this->t('Both Keycloak field and Drupal field must be specified if one is filled.'));
          continue;
        }

        // Check for duplicate Keycloak fields.
        if (in_array($row['keycloak_field'], $keycloak_fields)) {
          $form_state->setError($form['sync_settings']['field_mappings'][$delta]['keycloak_field'],
            $this->t('Duplicate Keycloak field found: @field', ['@field' => $row['keycloak_field']]));
        }
        $keycloak_fields[] = $row['keycloak_field'];

        // Check for duplicate Drupal fields.
        if (in_array($row['drupal_field'], $drupal_fields)) {
          $form_state->setError($form['sync_settings']['field_mappings'][$delta]['drupal_field'],
            $this->t('Duplicate Drupal field found: @field', ['@field' => $row['drupal_field']]));
        }
        $drupal_fields[] = $row['drupal_field'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory()
      ->getEditable('keycloak_user_sync.settings');

    $values = $form_state->getValue('field_mappings');
    $mappings = [];

    if (!empty($values)) {
      foreach ($values as $row) {
        if (!empty($row['keycloak_field']) && !empty($row['drupal_field'])) {
          $mappings[] = [
            'keycloak_field' => $row['keycloak_field'],
            'source' => $row['source'],
            'drupal_field' => $row['drupal_field'],
          ];
        }
      }
    }

    // Ensure at least the email mapping exists.
    if (empty($mappings)) {
      $mappings = [
        [
          'keycloak_field' => 'email',
          'source' => 'user',
          'drupal_field' => 'mail',
        ],
      ];
    }

    $config->set('field_mappings', $mappings);

    $insert_settings = $form_state->getValue('insert_settings');
    $config
      ->set('insert_email_verified', $insert_settings['email_verified'])
      ->set('insert_required_actions', array_filter($insert_settings['required_actions']));

    $update_settings = $form_state->getValue('update_settings');
    $config
      ->set('update_email_verified', $update_settings['email_verified'])
      ->set('update_required_actions', array_filter($update_settings['required_actions']))
      ->set('update_existing_fields', $update_settings['update_existing_fields'])
      ->set('update_email_field', $update_settings['update_email_field']);

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
