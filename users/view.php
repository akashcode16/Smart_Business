<?php
/**
 * User Management - View User
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role('Admin');

$current_user = get_logged_in_user();
$page_title = 'User Details';
$active_menu = 'users';

$user_id_param = (int)($_GET['id'] ?? 0);
if ($user_id_param <= 0) {
    set_flash('danger', "Invalid user specified.");
    header("Location: index.php");
    exit;
}

// Fetch user profile
$query = "SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id_param);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$user_data) {
    set_flash('danger', "User not found.");
    header("Location: index.php");
    exit;
}

// Fetch user's current inventory if business role
$stock_query = "SELECT s.quantity, s.updated_at, p.product_id, p.product_name, p.unit, c.category_name 
                FROM stock s 
                JOIN products p ON s.product_id = p.id 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE s.user_id = ? AND s.quantity > 0 
                ORDER BY s.quantity DESC";
$stock_stmt = mysqli_prepare($conn, $stock_query);
mysqli_stmt_bind_param($stock_stmt, "i", $user_id_param);
mysqli_stmt_execute($stock_stmt);
$stock_result = mysqli_stmt_get_result($stock_stmt);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>User Profile: <?php echo e($user_data['user_id']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Admin / Users / Details</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="edit.php?id=<?php echo e($user_data['id']); ?>" class="btn btn-primary btn-sm">Edit User</a>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Users</a>
    </div>
</div>

<div class="page-content">
    <div class="grid-row">
        <!-- Account Info Card -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Account Profile</span>
                    <span class="badge-role badge-<?php echo strtolower($user_data['role_name']); ?>"><?php echo e($user_data['role_name']); ?></span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tr>
                            <th style="width: 150px;">Database ID</th>
                            <td>#<?php echo e($user_data['id']); ?></td>
                        </tr>
                        <tr>
                            <th>User ID</th>
                            <td><strong><?php echo e($user_data['user_id']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Full Name</th>
                            <td><?php echo e($user_data['full_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Registered Email</th>
                            <td><?php echo e($user_data['email'] ?? 'Not set'); ?></td>
                        </tr>
                        <tr>
                            <th>Assigned Role</th>
                            <td><strong><?php echo e($user_data['role_name']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Account Status</th>
                            <td>
                                <?php if ($user_data['status'] === 'Active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Email Verified</th>
                            <td>
                                <?php if ((int)$user_data['is_verified'] === 1): ?>
                                    <span class="badge badge-success">&check; Verified</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pending Verification</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Google Account</th>
                            <td>
                                <?php if (!empty($user_data['google_id'])): ?>
                                    <span style="color: #4285F4; font-weight: bold;">Linked</span> (<?php echo e($user_data['google_email']); ?>)
                                <?php else: ?>
                                    <span style="color: #888;">Not linked</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Registration Date</th>
                            <td><?php echo date('d M Y, h:i A', strtotime($user_data['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Inventory Summary Card (For Stockiest/Distributor/Retailer) -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Current Inventory Holdings</span>
                    <span class="badge badge-info"><?php echo mysqli_num_rows($stock_result); ?> Product(s) in Stock</span>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($stock_result) === 0): ?>
                        <div style="padding: 20px; text-align: center; color: #888;">
                            No inventory items currently in stock for this account.
                        </div>
                    <?php else: ?>
                        <table class="data-table" style="margin-top: 0;">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Category</th>
                                    <th style="width: 100px; text-align: right;">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($st = mysqli_fetch_assoc($stock_result)): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($st['product_name']); ?></strong>
                                            <div style="font-size: 11px; color: #777;">Code: <?php echo e($st['product_id']); ?></div>
                                        </td>
                                        <td><?php echo e($st['category_name'] ?? '—'); ?></td>
                                        <td style="text-align: right;">
                                            <strong><?php echo number_format($st['quantity']); ?></strong> <?php echo e($st['unit']); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if ($stock_stmt) {
    mysqli_stmt_close($stock_stmt);
}
include '../includes/footer.php';
?>
