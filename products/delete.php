<?php
/**
 * Product Management - Safe Product Deletion
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
    set_flash('danger', "Invalid product ID.");
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT id, product_id, product_name FROM products WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$prod = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$prod) {
    set_flash('danger', "Product not found.");
    header("Location: index.php");
    exit;
}

// Check dependencies
// 1. Stock
$st_chk = mysqli_query($conn, "SELECT COUNT(*) as total FROM stock WHERE product_id = {$id} AND quantity > 0");
$st_count = (int)(mysqli_fetch_assoc($st_chk)['total'] ?? 0);

// 2. Purchases
$pi_chk = mysqli_query($conn, "SELECT COUNT(*) as total FROM purchase_items WHERE product_id = {$id}");
$pi_count = (int)(mysqli_fetch_assoc($pi_chk)['total'] ?? 0);

// 3. Transfers
$tr_chk = mysqli_query($conn, "SELECT COUNT(*) as total FROM stock_transfers WHERE product_id = {$id}");
$tr_count = (int)(mysqli_fetch_assoc($tr_chk)['total'] ?? 0);

// 4. Sales
$si_chk = mysqli_query($conn, "SELECT COUNT(*) as total FROM sale_items WHERE product_id = {$id}");
$si_count = (int)(mysqli_fetch_assoc($si_chk)['total'] ?? 0);

if ($st_count > 0 || $pi_count > 0 || $tr_count > 0 || $si_count > 0) {
    set_flash('danger', "Cannot delete product '{$prod['product_name']}' ({$prod['product_id']}) because it has linked business records (purchases, transfers, sales or inventory). Please mark it as Inactive instead to maintain audit integrity.");
    header("Location: index.php");
    exit;
}

// Clean up zero stock entries if any
mysqli_query($conn, "DELETE FROM stock WHERE product_id = {$id}");

// Delete product
$del = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
mysqli_stmt_bind_param($del, "i", $id);
if (mysqli_stmt_execute($del)) {
    set_flash('success', "Product '{$prod['product_name']}' ({$prod['product_id']}) was deleted successfully.");
} else {
    set_flash('danger', "Failed to delete product: " . mysqli_error($conn));
}
mysqli_stmt_close($del);

header("Location: index.php");
exit;
