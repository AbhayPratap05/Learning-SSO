<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/config.php';

// Check & get a valid Access Token
function getValidAccessToken(array $config): ?string
{
    // Check if Token is expired
    if (time() >= ($_SESSION['expires_at'] ?? 0)) {
        if (!refreshToken($config)) {
            return null; // Refresh failed
        }
    }
    return $_SESSION['access_token'] ?? null;
}

// Refresh Token 
function refreshToken(array $config): bool
{
    $issuer   = rtrim($config['KEYCLOAK_BASE_URL'], '/') . '/realms/' . $config['REALM'];
    $tokenUrl = $issuer . '/protocol/openid-connect/token';

    $data = [
        'grant_type'    => 'refresh_token',
        'refresh_token' => $_SESSION['refresh_token'] ?? '',
        'client_id'     => $config['CLIENT_ID'],
        'client_secret' => $config['CLIENT_SECRET'],
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ]
    ];

    $context = stream_context_create($options);
    $result  = file_get_contents($tokenUrl, false, $context);

    if ($result === FALSE) {
        return false;
    }

    $token = json_decode($result, true);
    if (isset($token['access_token'])) {
        $_SESSION['access_token']  = $token['access_token'];
        $_SESSION['id_token']      = $token['id_token'];
        $_SESSION['refresh_token'] = $token['refresh_token'];
        $_SESSION['expires_at']    = time() + $token['expires_in'];
        return true;
    }

    return false;
}

// Get valid token
$accessToken = getValidAccessToken($config);

if (!$accessToken) {
    http_response_code(401);
    echo "Not logged in or unable to refresh session.";
    exit;
}

// Call external API
$url = "https://httpbin.org/bearer";

$options = [
    'http' => [
        'header'  => "Authorization: Bearer " . $accessToken . "\r\n",
        'method'  => 'GET',
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

if ($result === FALSE) {
    http_response_code(500);
    echo "Error calling API.";
    exit;
}

// Show response
header("Content-Type: application/json");
echo $result;
