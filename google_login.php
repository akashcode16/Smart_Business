<?php
/**
 * Initiate Google Sign-In Flow
 * Smart Business Management System
 */

require_once 'config/google_oauth.php';
require_once 'includes/auth.php';

// Redirect if already logged in
redirect_if_logged_in();

// Check if credentials are configured
if (!is_google_oauth_configured()) {
    $_SESSION['auth_error'] = "Google Sign-In is not yet configured. Please configure your Google Client ID and Client Secret in config/google_oauth.php.";
    header("Location: login.php");
    exit;
}

// Redirect to Google authorization endpoint
$auth_url = get_google_auth_url();
header("Location: " . $auth_url);
exit;
?>
