<?php
require_once __DIR__ . '/../config/database.php';
$r = mysqli_query($conn, "SELECT id, user_id, full_name, email, role_id, status, is_verified, token_expiry, verification_token FROM users");
while ($u = mysqli_fetch_assoc($r)) {
    echo "ID: {$u['id']} | UID: {$u['user_id']} | Name: {$u['full_name']} | Email: {$u['email']} | Role: {$u['role_id']} | Status: {$u['status']} | Verified: {$u['is_verified']} | Expiry: {$u['token_expiry']} | Token: " . ($u['verification_token'] ?? 'NULL') . "\n";
}
