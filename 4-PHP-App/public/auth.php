<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/config.php';
$issuer = rtrim($config['KEYCLOAK_BASE_URL'], '/') . '/realms/' . $config['REALM'];

// to ensure valid access token is returned
function getAccessToken()
{
    if (!isset($_SESSION['access_token'])) {
        return null;
    }

    // Check if token expired
    if (time() >= $_SESSION['expires_at']) {
        // Try refresh
        if (!refreshToken()) {
            // Refresh failed → logout
            header("Location: logout.php");
            exit;
        }
    }

    return $_SESSION['access_token'];
}

// Refresh token using Keycloak
function refreshToken()
{
    global $config, $issuer;
    $tokenUrl = $issuer . '/protocol/openid-connect/token';

    $data = [
        'grant_type'    => 'refresh_token',
        'refresh_token' => $_SESSION['refresh_token'],
        'client_id'     => $config['CLIENT_ID'],
        'client_secret' => $config['CLIENT_SECRET']
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ]
    ];

    $context  = stream_context_create($options);
    $result = file_get_contents($tokenUrl, false, $context);

    if ($result === FALSE) {
        return false;
    }

    $token = json_decode($result, true);

    if (isset($token['access_token'])) {
        $_SESSION['access_token'] = $token['access_token'];
        $_SESSION['id_token'] = $token['id_token'];
        $_SESSION['refresh_token'] = $token['refresh_token'];
        $_SESSION['expires_at'] = time() + $token['expires_in'];

        // Decode fresh ID token claims
        list(, $idp,) = explode('.', $_SESSION['id_token']);
        $_SESSION['id_token_claims'] = json_decode(base64_decode(strtr($idp, '-_', '+/')), true);

        // Decode fresh Access token claims
        list(, $acp,) = explode('.', $_SESSION['access_token']);
        $_SESSION['access_token_claims'] = json_decode(base64_decode(strtr($acp, '-_', '+/')), true);

        // Update roles/resources from new access token
        $_SESSION['realm_roles']    = $_SESSION['access_token_claims']['realm_access']['roles'] ?? [];
        $_SESSION['resource_access'] = $_SESSION['access_token_claims']['resource_access']['php-app'] ?? [];
        return true;
    }

    return false;
}

function requireLogin()
{
    if (!isset($_SESSION['access_token'])) {
        header("Location: login.php");
        exit;
    }
}

function hasRealmRole($role)
{
    return in_array($role, $_SESSION['realm_roles'] ?? []);
}

function hasClientRole($role)
{
    return isset($_SESSION['resource_access']['roles']) &&
        in_array($role, $_SESSION['resource_access']['roles']);
}

function requireRealmRole($role)
{
    if (!hasRealmRole($role)) {
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>You do not have the required realm role: <strong>$role</strong></p>";
        exit;
    }
}

function requireClientRole($role)
{
    if (!hasClientRole($role)) {
        http_response_code(403);
        echo "<h1>403 Forbidden</h1><p>You do not have the required client role: <strong>$role</strong></p>";
        exit;
    }
}
