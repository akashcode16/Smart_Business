<?php
/**
 * Authentication and Role Authorization Helper
 * Smart Business Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!defined('BASE_URL')) {
    define('BASE_URL', '/smart_business/');
}

/**
 * Check if a user is currently logged in
 *
 * @return bool
 */
function is_logged_in() {
    return isset($_SESSION['user_db_id']) && !empty($_SESSION['user_db_id']);
}

/**
 * Get the current logged-in user data from session
 *
 * @return array|null
 */
function get_logged_in_user() {
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id'        => $_SESSION['user_db_id'],
        'user_id'   => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'],
        'role_id'   => $_SESSION['role_id'],
        'role_name' => $_SESSION['role_name']
    ];
}

/**
 * Redirect logged in users to their respective role dashboard/home
 */
function redirect_if_logged_in($base_path = BASE_URL) {
    if (is_logged_in()) {
        $role = strtolower($_SESSION['role_name']);
        switch ($role) {
            case 'admin':
                header("Location: " . $base_path . "admin/index.php");
                exit;
            case 'stockiest':
                header("Location: " . $base_path . "stockiest/index.php");
                exit;
            case 'distributor':
                header("Location: " . $base_path . "distributor/index.php");
                exit;
            case 'retailer':
                header("Location: " . $base_path . "retailer/index.php");
                exit;
            default:
                header("Location: " . $base_path . "login.php");
                exit;
        }
    }
}

/**
 * Ensure user is logged in; otherwise redirect to login page
 *
 * @param string $login_path Absolute or relative path to login.php
 */
function require_login($login_path = '') {
    if (empty($login_path)) {
        $login_path = BASE_URL . 'login.php';
    }
    if (!is_logged_in()) {
        header("Location: " . $login_path);
        exit;
    }
}

/**
 * Ensure user has the required role; otherwise redirect or deny access
 *
 * @param string|array $allowed_roles Role name or array of allowed role names
 * @param string $redirect_path Fallback redirection path
 */
function require_role($allowed_roles, $redirect_path = '') {
    if (empty($redirect_path)) {
        $redirect_path = BASE_URL . 'login.php';
    }
    require_login($redirect_path);
    
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    $current_role = $_SESSION['role_name'] ?? '';
    
    // Case-insensitive check
    $allowed_roles_lower = array_map('strtolower', $allowed_roles);
    if (!in_array(strtolower($current_role), $allowed_roles_lower)) {
        // User does not have permission - redirect to their authorized home or deny
        $_SESSION['auth_error'] = "Unauthorized access. You do not have permission to view that page.";
        redirect_if_logged_in(BASE_URL);
        exit;
    }
}
?>
