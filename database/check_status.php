<?php
require_once __DIR__ . '/../config/database.php';

echo "=== DATABASE STATUS ===\n";
$tables = mysqli_query($conn, 'SHOW TABLES');
while ($t = mysqli_fetch_row($tables)) {
    $c = mysqli_fetch_row(mysqli_query($conn, 'SELECT COUNT(*) FROM `' . $t[0] . '`'));
    echo str_pad($t[0], 20) . ": " . $c[0] . " rows\n";
}

echo "\n=== EXISTING USERS ===\n";
$users = mysqli_query($conn, "SELECT u.id, u.user_id, u.full_name, u.email, r.role_name, u.status, u.is_verified FROM users u JOIN roles r ON u.role_id = r.id");
while ($u = mysqli_fetch_assoc($users)) {
    echo "ID: {$u['id']} | User ID: {$u['user_id']} | Name: {$u['full_name']} | Role: {$u['role_name']} | Status: {$u['status']}\n";
}

echo "\n=== EXISTING CATEGORIES ===\n";
$cats = mysqli_query($conn, "SELECT id, category_name FROM categories");
while ($c = mysqli_fetch_assoc($cats)) {
    echo "ID: {$c['id']} | Name: {$c['category_name']}\n";
}
