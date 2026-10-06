<?php
/**
 * Main Entry Point
 * Smart Business Management System
 */

require_once 'includes/auth.php';

// If logged in, redirect to user's dashboard; otherwise redirect to login page
if (is_logged_in()) {
    redirect_if_logged_in();
} else {
    header("Location: login.php");
    exit;
}
?>