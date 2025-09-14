<?php

namespace Drupal\keycloak\Plugin\OpenIDConnectClient;

use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Role matcher trait.
 *
 * Provides helper methods for matching Keycloak user group rules
 * to Drupal user roles.
 */
trait KeycloakRoleMatcherTrait {

  /**
   * Retrieve Keycloak groups from user information.
   *
   * @param string $attribute
   *   Keycloak groups claim identifier.
   * @param array $userinfo
   *   User info array as returned by
   *   \Drupal\keycloak\Plugin\OpenIDConnectClient\Keycloak::retrieveUserInfo().
   *
   * @return array
   *   Extracted user groups.
   */
  protected function getGroups($attribute, array $userInfo): array {
    // Whether the user information is empty.
    if (empty($userInfo)) {
      // No group attribute. Return empty array.
      return [];
    }

    // Walk the attribute path to retrieve the user groups.
    $attribute_path = explode('.', $attribute);
    while (!empty($attribute_path)) {
      $segment = array_shift($attribute_path);

      if (isset($userInfo[$segment])) {
        $userInfo = $userInfo[$segment];
      }
      else {
        $userInfo = [];
        break;
      }
    }

    return $userInfo;
  }

  /**
   * Return split user groups.
   *
   * Keycloak user groups can be nested. This helper method flattens
   * nested group paths to an one-level array of group path segments.
   *
   * @param array $groups
   *   Array of user group paths as returned by Keycloak.
   * @param int $max_level
   *   (Optional) Maximum level to split into the result. If a level
   *   greater than 0 is given, the splitting will ignore user groups
   *   with a higher nesting level. Level counting starts at 1. If a
   *   maximum of 0 is given, ALL levels will be included.
   *   Defaults to 0.
   *
   * @return array
   *   Transformed user groups array.
   */
  protected function getSplitGroups(array $groups, $max_level = 0): array {
    $target = [];

    foreach ($groups as $group) {
      $segments = explode('/', trim($group, '/'));
      if ($max_level > 0) {
        $segments = array_slice($segments, 0, $max_level);
      }
      $target = array_merge($target, $segments);
    }

    return array_unique($target);
  }

  /**
   * Return all available user roles as options array.
   *
   * @param bool $exclude_locked
   *   (Optional) Whether to exclude the system locked roles 'Anonymous' and
   *   'Authenticated'.
   *   Defaults to TRUE.
   *
   * @return array
   *   Array of user roles that can be used as select / radio / checkbox
   *   options.
   */
  public function getRoleOptions($exclude_locked = TRUE): array {
    $role_options = [];
    $roles = Role::loadMultiple();
    foreach ($roles as $role) {
      $role_id = $role->id();
      if ($exclude_locked && ($role_id == RoleInterface::ANONYMOUS_ID || $role_id == RoleInterface::AUTHENTICATED_ID)) {
        continue;
      }
      $role_options[$role_id] = $role->label();
    }
    return $role_options;
  }

  /**
   * Return an options array of available role evaluation operations.
   *
   * @return array
   *   Array of available role evaluation operations that can be used
   *   as select / radio / checkbox options.
   */
  public function getEvalOperationOptions(): array {
    return [
      'equal' => $this->t('exact match'),
      'not_equal' => $this->t('no match'),
      'starts_with' => $this->t('starts with'),
      'starts_not_with' => $this->t('starts not with'),
      'ends_with' => $this->t('ends with'),
      'ends_not_with' => $this->t('ends not with'),
      'contains' => $this->t('contains'),
      'contains_not' => $this->t('contains not'),
      'empty' => $this->t('no groups given'),
      'not_empty' => $this->t('any group given'),
      'regex' => $this->t('regex match'),
      'not_regex' => $this->t('no regex match'),
    ];
  }

  /**
   * Return a regex evaluation pattern for user group role rules.
   *
   * @param string $pattern
   *   User entered search pattern.
   * @param string $operation
   *   Evaluation operation to conduct.
   * @param bool $case_sensitive
   *   Whether the resulting pattern shall be case-sensitive.
   *
   * @return string
   *   PCRE pattern for role rule evaluation.
   */
  protected function getEvalPattern($pattern, $operation = 'equal', $case_sensitive = TRUE): string {
    // Quote regular expression characters in regular pattern string.
    if ($operation !== 'regex' && $operation !== 'not_regex') {
      $pattern = preg_quote($pattern, '/');
    }

    // Construct a PCRE pattern for the given operation.
    switch ($operation) {
      case 'starts_with':
      case 'starts_not_with':
        $pattern = '/^' . $pattern . '/';
        break;

      case 'ends_with':
      case 'ends_not_with':
        $pattern = '/' . $pattern . '$/';
        break;

      case 'contains':
      case 'contains_not':
      case 'regex':
      case 'not_regex':
        $pattern = '/' . $pattern . '/';
        break;

      case 'not_equal':
      default:
        $pattern = '/^' . $pattern . '$/';
        break;

    }

    // Whether the pattern shall not be case-sensitive.
    if (!$case_sensitive) {
      $pattern .= 'i';
    }

    return $pattern;
  }

  /**
   * Check, if the given rule matches the user groups.
   *
   * This method applies the given user group rule to the user groups
   * and evaluates, whether the rule action should be executed or not.
   *
   * @param array $groups
   *   User groups to evaluate.
   * @param array $rule
   *   User group rule to evaluate.
   *
   * @return bool
   *   TRUE, if the rule matches the groups, FALSE otherwise.
   */
  protected function evalRoleRule(array $groups, array $rule): bool {
    // Whether teh rule is disabled.
    if (!$rule['enabled']) {
      return FALSE;
    }

    $operation = $rule['operation'];

    // Check the 'empty' operation.
    if ($operation === 'empty') {
      return empty($groups);
    }

    // Check the 'not_empty' operation.
    if ($operation === 'not_empty') {
      return !empty($groups);
    }

    $pattern = $this->getEvalPattern(
      $rule['pattern'],
      $operation,
      $rule['case_sensitive'] ?? TRUE
    );

    // Apply the pattern to the user groups.
    $result = preg_grep($pattern, $groups);

    // Evaluate the result.
    // 'not' operations are TRUE, if the result array is empty.
    if (
      $operation === 'not_equal' ||
      $operation === 'starts_not_with' ||
      $operation === 'ends_not_with' ||
      $operation === 'contains_not' ||
      $operation === 'not_regex'
    ) {
      return empty($result);
    }

    // All other operations are TRUE, if the result array is not empty.
    return !empty($result);
  }

}
