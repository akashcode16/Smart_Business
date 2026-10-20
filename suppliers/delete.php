<?php
/**
 * Supplier Management - Delete Supplier Safely
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
    set_flash('danger', "Invalid supplier ID.");
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, supplier_id, supplier_name FROM suppliers WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$sup = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$sup) {
    set_flash('danger', "Supplier not found.");
    header("Location: index.php");
    exit;
}

// Check if purchases exist
$p_chk = mysqli_query($conn, "SELECT COUNT(*) as total FROM purchases WHERE supplier_id = {$id}");
$p_count = (int)(mysqli_fetch_assoc($p_chk)['total'] ?? 0);

if ($p_count > 0) {
    set_flash('danger', "Cannot delete supplier '{$sup['supplier_name']}' because {$p_count} purchase order(s) are linked to them. Please mark them as Inactive instead to maintain historical records.");
    header("Location: index.php");
    exit;
}

$del = mysqli_prepare($conn, "DELETE FROM suppliers WHERE id = ?");
mysqli_stmt_bind_param($del, "i", $id);
if (mysqli_stmt_execute($del)) {
    set_flash('success', "Supplier '{$sup['supplier_name']}' ({$sup['supplier_id']}) was deleted successfully.");
} else {
    set_flash('danger', "Failed to delete supplier: " . mysqli_error($conn));
}
mysqli_stmt_close($del);

header("Location: index.php");
exit;
