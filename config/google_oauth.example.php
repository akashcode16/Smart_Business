<?php
/**
 * Google OAuth 2.0 Configuration Example Template
 * Smart Business Management System
 * 
 * Copy this file to config/google_oauth.php and replace with your Google Cloud Console credentials.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========================================================
// GOOGLE OAUTH CREDENTIALS (REPLACE WITH YOUR VALUES)
// ========================================================
define('GOOGLE_CLIENT_ID', 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'YOUR_GOOGLE_CLIENT_SECRET');
define('GOOGLE_REDIRECT_URI', 'http://localhost/smart_business/google_callback.php');

// Google OAuth 2.0 Endpoints
define('GOOGLE_AUTH_URL', 'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v3/userinfo');

function is_google_oauth_configured()
{
    return (
        defined('GOOGLE_CLIENT_ID') &&
        GOOGLE_CLIENT_ID !== 'YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com' &&
        !empty(GOOGLE_CLIENT_ID) &&
        defined('GOOGLE_CLIENT_SECRET') &&
        GOOGLE_CLIENT_SECRET !== 'YOUR_GOOGLE_CLIENT_SECRET' &&
        !empty(GOOGLE_CLIENT_SECRET)
    );
}

function get_google_auth_url()
{
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account'
    ];

    return GOOGLE_AUTH_URL . '?' . http_build_query($params);
}

function exchange_code_for_token($code)
{
    $params = [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, GOOGLE_TOKEN_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        return json_decode($response, true);
    }

    return false;
}

function get_verified_google_user_info($access_token)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, GOOGLE_USERINFO_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['sub']) && isset($data['email']) && (!isset($data['email_verified']) || $data['email_verified'] === true)) {
            return $data;
        }
    }

    return false;
}
