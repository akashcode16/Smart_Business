<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

echo "Testing Database and Auth Setup...\n";

$q = mysqli_query($conn, "SELECT * FROM users WHERE user_id = 'ADM001'");
if ($u = mysqli_fetch_assoc($q)) {
    echo "Found user: " . $u['user_id'] . " (" . $u['full_name'] . ")\n";
    $pass_check = password_verify('Admin@123', $u['password']);
    echo "Password verify for 'Admin@123': " . ($pass_check ? "PASSED" : "FAILED") . "\n";
    echo "Account Status: " . $u['status'] . "\n";
    echo "Email Verified: " . ($u['is_verified'] ? "YES" : "NO") . "\n";
} else {
    echo "ADM001 not found!\n";
}
