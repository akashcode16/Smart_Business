<?php
/**
 * Customer Management - Safe Customer Deletion
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();

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
    set_flash('danger', "Invalid customer ID.");
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, customer_id, customer_name, created_by FROM customers WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$cust = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$cust) {
    set_flash('danger', "Customer not found.");
    header("Location: index.php");
    exit;
}

// Access check: non-admin can only delete their own customer
if (!is_admin() && !empty($cust['created_by']) && (int)$cust['created_by'] !== (int)$current_user['id']) {
    set_flash('danger', "You do not have permission to delete this customer.");
    header("Location: index.php");
    exit;
}

// Check linked sales orders
$s_chk = mysqli_query($conn, "SELECT COUNT(*) as total FROM sales WHERE customer_id = {$id}");
$s_count = (int)(mysqli_fetch_assoc($s_chk)['total'] ?? 0);

if ($s_count > 0) {
    set_flash('danger', "Cannot delete customer '{$cust['customer_name']}' because {$s_count} sales invoice(s) are linked to them. Please mark as Inactive instead to maintain billing history.");
    header("Location: index.php");
    exit;
}

$del = mysqli_prepare($conn, "DELETE FROM customers WHERE id = ?");
mysqli_stmt_bind_param($del, "i", $id);
if (mysqli_stmt_execute($del)) {
    set_flash('success', "Customer '{$cust['customer_name']}' ({$cust['customer_id']}) was deleted successfully.");
} else {
    set_flash('danger', "Failed to delete customer: " . mysqli_error($conn));
}
mysqli_stmt_close($del);

header("Location: index.php");
exit;
