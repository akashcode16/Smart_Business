<?php
/**
 * Google OAuth 2.0 Callback Handler
 * Smart Business Management System
 */

require_once 'config/database.php';
require_once 'config/google_oauth.php';
require_once 'includes/auth.php';

// Check for error returned by Google
if (isset($_GET['error'])) {
    $_SESSION['auth_error'] = "Google authentication failed: " . htmlspecialchars($_GET['error']);
    header("Location: login.php");
    exit;
}

// 1. Verify Anti-CSRF state token
$incoming_state = $_GET['state'] ?? '';
$session_state = $_SESSION['oauth_state'] ?? '';

if (empty($incoming_state) || empty($session_state) || !hash_equals($session_state, $incoming_state)) {
    $_SESSION['auth_error'] = "Invalid authentication state. Please try logging in again.";
    unset($_SESSION['oauth_state']);
    header("Location: login.php");
    exit;
}

// Clear used state
unset($_SESSION['oauth_state']);

// 2. Validate authorization code
$auth_code = $_GET['code'] ?? '';
if (empty($auth_code)) {
    $_SESSION['auth_error'] = "Authorization code not received from Google.";
    header("Location: login.php");
    exit;
}

// 3. Exchange authorization code for token
$token_data = exchange_code_for_token($auth_code);
if (!$token_data || !isset($token_data['access_token'])) {
    $_SESSION['auth_error'] = "Failed to obtain access token from Google.";
    header("Location: login.php");
    exit;
}

// 4. Fetch verified user info from Google's official endpoint
$google_user = get_verified_google_user_info($token_data['access_token']);
if (!$google_user || !isset($google_user['email']) || !isset($google_user['sub'])) {
    $_SESSION['auth_error'] = "Failed to verify Google account credentials.";
    header("Location: login.php");
    exit;
}

$google_id = $google_user['sub'];
$google_email = strtolower(trim($google_user['email']));

// 5. Query existing user linked by google_id or google_email
$query = "SELECT u.id, u.user_id, u.full_name, u.role_id, u.status, u.google_id, u.google_email, r.role_name 
          FROM users u 
          JOIN roles r ON u.role_id = r.id 
          WHERE u.google_id = ? OR (LOWER(u.google_email) = ? AND u.google_email IS NOT NULL AND u.google_email != '') 
          LIMIT 1";

$user = null;
if ($stmt = mysqli_prepare($conn, $query)) {
    mysqli_stmt_bind_param($stmt, "ss", $google_id, $google_email);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// 6. Enforce security: Account MUST be pre-registered by Admin
if (!$user) {
    // Unregistered Google Account - DO NOT create a new user or role
    $_SESSION['auth_error'] = "This Google account (" . htmlspecialchars($google_email) . ") is not registered in the system. Please contact the administrator.";
    header("Location: login.php");
    exit;
}

// 7. Check if user account is Active
if ($user['status'] !== 'Active') {
    $_SESSION['auth_error'] = "Your account is inactive. Please contact the administrator.";
    header("Location: login.php");
    exit;
}

// 8. If google_id is not yet linked on this user, link it now
if (empty($user['google_id'])) {
    $update_query = "UPDATE users SET google_id = ?, google_email = ? WHERE id = ?";
    if ($update_stmt = mysqli_prepare($conn, $update_query)) {
        mysqli_stmt_bind_param($update_stmt, "ssi", $google_id, $google_email, $user['id']);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);
    }
}

// 9. Login success: Establish secure session and role routing
session_regenerate_id(true);

$_SESSION['user_db_id'] = (int)$user['id'];
$_SESSION['user_id']    = $user['user_id'];
$_SESSION['full_name']  = $user['full_name'];
$_SESSION['role_id']    = (int)$user['role_id'];
$_SESSION['role_name']  = $user['role_name'];

// Redirect to respective authorized role dashboard
redirect_if_logged_in();
exit;
?>
