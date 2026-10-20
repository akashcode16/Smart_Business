<?php
/**
 * User Management - Toggle User Status (Active / Inactive)
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role('Admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    set_flash('danger', "Invalid security token. Please try again.");
    header("Location: index.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', "Invalid user ID.");
    header("Location: index.php");
    exit;
}

// Fetch user
$stmt = mysqli_prepare($conn, "SELECT u.id, u.user_id, u.full_name, u.status, r.role_name 
                               FROM users u 
                               JOIN roles r ON u.role_id = r.id 
                               WHERE u.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$user) {
    set_flash('danger', "User not found.");
    header("Location: index.php");
    exit;
}

$new_status = ($user['status'] === 'Active') ? 'Inactive' : 'Active';

// Safety check: Prevent deactivating the last active Admin
if ($user['status'] === 'Active' && $user['role_name'] === 'Admin') {
    $count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'Admin' AND u.status = 'Active'");
    $admin_count = (int)(mysqli_fetch_assoc($count_res)['total'] ?? 0);
    if ($admin_count <= 1) {
        set_flash('danger', "Safety restriction: You cannot deactivate the only remaining active Administrator account.");
        header("Location: index.php");
        exit;
    }
}

// Update status
$up = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE id = ?");
mysqli_stmt_bind_param($up, "si", $new_status, $id);
if (mysqli_stmt_execute($up)) {
    set_flash('success', "Status for user {$user['user_id']} ({$user['full_name']}) has been changed to {$new_status}.");
} else {
    set_flash('danger', "Failed to update user status.");
}
mysqli_stmt_close($up);

header("Location: index.php");
exit;
