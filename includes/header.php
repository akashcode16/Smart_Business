<?php
/**
 * Common Header Layout
 * Smart Business Management System
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/auth.php';

if (!defined('BASE_URL')) {
    define('BASE_URL', '/smart_business/');
}

$page_title = isset($page_title) ? $page_title : 'Smart Business Management System';
$active_menu = isset($active_menu) ? $active_menu : '';

// Ensure user is logged in
require_login(BASE_URL . 'login.php');

$current_user = get_logged_in_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Smart Business Management System</title>
    <!-- Business Application Stylesheet with Cache Busting -->
    <?php
    $css_path = dirname(__DIR__) . '/assets/css/style.css';
    $css_version = file_exists($css_path) ? filemtime($css_path) : time();
    ?>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo $css_version; ?>">
</head>
<body>
    <div class="app-container">
        <!-- Top Navigation Bar -->
        <header class="top-navbar">
            <div class="navbar-brand">
                <a href="<?php echo BASE_URL; ?>index.php">ONLINE SMART BUSINESS</a>
            </div>
            
            <div class="navbar-user-info">
                <span class="user-greeting">Logged in as: <strong><?php echo htmlspecialchars($current_user['full_name'] ?? ''); ?></strong></span>
                <span class="badge-role badge-<?php echo strtolower($current_user['role_name'] ?? ''); ?>"><?php echo htmlspecialchars($current_user['role_name'] ?? ''); ?></span>
                <span class="user-id-tag">(ID: <?php echo htmlspecialchars($current_user['user_id'] ?? ''); ?>)</span>
                <a href="<?php echo BASE_URL; ?>logout.php" class="btn-logout">Logout</a>
            </div>
        </header>

        <!-- Main Body Wrapper -->
        <div class="layout-body">
            <!-- Sidebar Navigation -->
            <?php include __DIR__ . '/sidebar.php'; ?>

            <!-- Main Content Container -->
            <main class="app-main">
