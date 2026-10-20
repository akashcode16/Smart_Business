<?php
/**
 * User Management - Safe User Deletion
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role('Admin');

$current_user = get_logged_in_user();

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

// Cannot delete self
if ($id === (int)$current_user['id']) {
    set_flash('danger', "You cannot delete your own logged-in account.");
    header("Location: index.php");
    exit;
}

// Fetch user
$stmt = mysqli_prepare($conn, "SELECT u.id, u.user_id, u.full_name, r.role_name 
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

// Check if user is the last active admin
if ($user['role_name'] === 'Admin') {
    $count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'Admin' AND u.status = 'Active'");
    $admin_count = (int)(mysqli_fetch_assoc($count_res)['total'] ?? 0);
    if ($admin_count <= 1) {
        set_flash('danger', "Safety restriction: You cannot delete the only remaining active Administrator account.");
        header("Location: index.php");
        exit;
    }
}

// Check for business dependencies
// 1. Stock with quantity > 0
$stock_chk = mysqli_query($conn, "SELECT COUNT(*) as total FROM stock WHERE user_id = {$id} AND quantity > 0");
$stock_count = (int)(mysqli_fetch_assoc($stock_chk)['total'] ?? 0);

// 2. Purchases
$purch_chk = mysqli_query($conn, "SELECT COUNT(*) as total FROM purchases WHERE user_id = {$id}");
$purch_count = (int)(mysqli_fetch_assoc($purch_chk)['total'] ?? 0);

// 3. Transfers
$trans_chk = mysqli_query($conn, "SELECT COUNT(*) as total FROM stock_transfers WHERE from_user_id = {$id} OR to_user_id = {$id}");
$trans_count = (int)(mysqli_fetch_assoc($trans_chk)['total'] ?? 0);

// 4. Sales
$sales_chk = mysqli_query($conn, "SELECT COUNT(*) as total FROM sales WHERE user_id = {$id}");
$sales_count = (int)(mysqli_fetch_assoc($sales_chk)['total'] ?? 0);

if ($stock_count > 0 || $purch_count > 0 || $trans_count > 0 || $sales_count > 0) {
    $reasons = [];
    if ($stock_count > 0) $reasons[] = "active stock ({$stock_count} item(s))";
    if ($purch_count > 0) $reasons[] = "purchase records ({$purch_count})";
    if ($trans_count > 0) $reasons[] = "stock transfer history ({$trans_count})";
    if ($sales_count > 0) $reasons[] = "sales orders ({$sales_count})";
    
    set_flash('danger', "Cannot delete user {$user['user_id']} because of linked business history: " . implode(', ', $reasons) . ". Please deactivate the account instead to maintain transaction integrity.");
    header("Location: index.php");
    exit;
}

// Delete user safely
$del_stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
mysqli_stmt_bind_param($del_stmt, "i", $id);
if (mysqli_stmt_execute($del_stmt)) {
    set_flash('success', "User account {$user['user_id']} ({$user['full_name']}) was deleted successfully.");
} else {
    set_flash('danger', "Failed to delete user: " . mysqli_error($conn));
}
mysqli_stmt_close($del_stmt);

header("Location: index.php");
exit;
