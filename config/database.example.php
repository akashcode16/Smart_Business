<?php
/**
 * Database Configuration Example Template
 * Smart Business Management System
 * 
 * Copy this file to config/database.php and adjust credentials as needed.
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'smart_business');
define('DB_PORT', 3306);

// Establish MySQLi connection
$conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection safely
if (!$conn) {
    error_log("Database Connection Error: " . mysqli_connect_error());
    die("A database connection error occurred. Please contact the system administrator.");
}

// Set character set to UTF-8
mysqli_set_charset($conn, "utf8mb4");
?>
