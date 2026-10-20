<?php
/**
 * Category Management - Toggle Status
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
    set_flash('danger', "Invalid security token.");
    header("Location: index.php");
    exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', "Invalid category ID.");
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, category_name, status FROM categories WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$cat = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$cat) {
    set_flash('danger', "Category not found.");
    header("Location: index.php");
    exit;
}

$new_status = ($cat['status'] === 'Active') ? 'Inactive' : 'Active';

$up = mysqli_prepare($conn, "UPDATE categories SET status = ? WHERE id = ?");
mysqli_stmt_bind_param($up, "si", $new_status, $id);
if (mysqli_stmt_execute($up)) {
    set_flash('success', "Category '{$cat['category_name']}' status changed to {$new_status}.");
} else {
    set_flash('danger', "Failed to update category status.");
}
mysqli_stmt_close($up);

header("Location: index.php");
exit;
