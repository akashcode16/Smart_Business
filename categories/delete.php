<?php
/**
 * Category Management - Delete Category Safely
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

$stmt = mysqli_prepare($conn, "SELECT id, category_name FROM categories WHERE id = ? LIMIT 1");
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

// Check if any products reference this category
$prod_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM products WHERE category_id = ?");
mysqli_stmt_bind_param($prod_stmt, "i", $id);
mysqli_stmt_execute($prod_stmt);
$prod_res = mysqli_stmt_get_result($prod_stmt);
$prod_count = (int)(mysqli_fetch_assoc($prod_res)['total'] ?? 0);
mysqli_stmt_close($prod_stmt);

if ($prod_count > 0) {
    set_flash('danger', "Cannot delete category '{$cat['category_name']}' because {$prod_count} product(s) are linked to it. Please reassign or delete linked products first, or deactivate the category instead.");
    header("Location: index.php");
    exit;
}

// Delete safely
$del = mysqli_prepare($conn, "DELETE FROM categories WHERE id = ?");
mysqli_stmt_bind_param($del, "i", $id);
if (mysqli_stmt_execute($del)) {
    set_flash('success', "Category '{$cat['category_name']}' was deleted successfully.");
} else {
    set_flash('danger', "Failed to delete category: " . mysqli_error($conn));
}
mysqli_stmt_close($del);

header("Location: index.php");
exit;
