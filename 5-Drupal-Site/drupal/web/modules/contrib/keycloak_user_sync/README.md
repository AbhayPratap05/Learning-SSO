# Keycloak User Sync

## INTRODUCTION

Keycloak User Sync synchronizes user data between Drupal and Keycloak. When
users are created, updated, or deleted in Drupal, these changes are
automatically reflected in Keycloak. The module also supports custom field
mappings from either the user account or various profile fields between Drupal
and Keycloak.

## REQUIREMENTS

This module requires the following:

* Drupal 10.x or 11.x
* Keycloak server (tested with version 26.x)
* PHP 8.1 or higher
* [Profile](https://www.drupal.org/project/profile) module (optional - for
  profile field mapping)

## INSTALLATION

Install the module as you would normally install a contributed Drupal module.
Visit
https://www.drupal.org/node/1897420 for further information.

## CONFIGURATION

### 1. Keycloak Service User Setup

1. Log into your Keycloak Admin Console
2. Navigate to your realm
3. Create a new client:

* Client ID: `drupal-sync` (or your preferred name)
* Client Protocol: `openid-connect`
* Switch 'Client authentication' on
* Authentication flow: `Service accounts roles`

4. After saving, go to the "Service accounts roles" tab and add the fowllowing
   roles:

* manage-users
* view-users
* query-users

5. Go to "Credentials" and use Client Authenticator "Client ID and Secret" and
   note the Client Secret

### 2. Custom Attributes in Keycloak

To allow custom fields to be synchronized:

1. Go to your realm settings
2. Click on "Clients" and select your dedicated client
3. Click on "Client Scopes" tab and click your dedicated client scope (eg.
   drupal-sync-dedicated)
4. Create a new mapper for each custom field:

* Mapper Type: `User Attribute`
* User Attribute: name of your custom field
* Token Claim Name: same as User Attribute
* Claim JSON Type: `String`

### 3. Drupal Settings

1. Add the following to your `settings.php` or `settings.local.php`:

```php

$settings['keycloak_user_sync.connection'] = [
  'url' => 'https://your-keycloak-server',
  'realm' => 'your-realm-name',
];

$settings['keycloak_user_sync.credentials'] = [
  'client_id' => 'your-client-id', // eg. drupal-sync
  'client_secret' => 'your-client-secret',
];
```

2. Go to `/admin/config/people/keycloak-user-sync`
3. Configure the user creation and user udpate settings. You can choose default
   actions a user needs to complete upon login. Please note: If you want to sync
   additional fields upon user creation, enable the "Update Keycloak fields with
   mapped Drupal fields" feature in the "User Update Settings" vertical tab.
4. Configure field mappings between Drupal and Keycloak

* add the name of the Keycloak field, select the source (User Account or
  Profile) and type in the machine name of the Drupal field, eg.
  field_firstname.

## USAGE

Once configured, the module will automatically:

* Create users in Keycloak when new users are created in Drupal
* Update user information in Keycloak when users are updated in Drupal
* Delete users in Keycloak when users are deleted in Drupal

IMPORTANT ASPECTS:

* Passwords are not synchronized by the module. Users must set passwords via
  Keycloak.
* Removing fields in Drupal does not remove field definitions in Keycloak.
* If you remove field mappings, the corresponding information in Keycloak will
  be removed during the next sync. If you want to keep the information in
  Keycloak, uncheck the "Update Keycloak fields with mapped Drupal fields"
  checkbox in the "User Update Settings" vertical tab.

### Field Mapping

The following Keycloak fields are mapped by default:

* username

Custom fields must be:

1. Added as User Attributes in Keycloak (see Configuration section)
2. Mapped in the module configuration

Example mapping:

* Keycloak Field: `firstName`
  - Source: User Account
  - Drupal Field: `field_first_name`
* Keycloak Field: `custom_field`
  - Source: Profile: Main
  - Drupal Field: `field_custom`

## TROUBLESHOOTING

Common issues:

1. Connection errors:

* Verify Keycloak URL and realm name (use 'Test connection' button)
* Check client secret
* Ensure service account has correct permissions

2. Sync failures:

* Check Drupal watchdog logs
* Verify field mappings exist in both systems
* Ensure custom fields are properly configured in Keycloak

## MAINTAINERS

* roromedia - https://www.drupal.org/u/roromedia

## DEVELOPMENT

Development is tracked
in [GitHub](https://github.com/roromedia/keycloak_user_sync)

Last updated: 2025-01-26

## CHANGELOG

* 1.0.0 (2025-01-26)
  - Initial release
  - Basic user synchronization
  - Field mapping interface
  - Support for Profile module fields
  - Custom attribute mapping

This README
follows [Drupal.org's module documentation guidelines](https://www.drupal.org/docs/develop/documenting-your-project/readme-template).
