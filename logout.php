<?php
/**
 * User Logout Page
 * Smart Business Management System
 */

require_once 'includes/auth.php';

// Unset all session variables
$_SESSION = [];

// Delete session cookie if active
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login page
header("Location: login.php?logged_out=1");
exit;
?>
