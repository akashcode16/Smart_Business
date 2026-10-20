<?php
/**
 * Customer Management - View Customer Details & Invoices
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Customer Profile';
$active_menu = 'customers';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', "Invalid customer specified.");
    header("Location: index.php");
    exit;
}

// Fetch customer
$stmt = mysqli_prepare($conn, "SELECT c.*, u.full_name as created_by_name, u.user_id as creator_user_id 
                               FROM customers c 
                               LEFT JOIN users u ON c.created_by = u.id 
                               WHERE c.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$customer) {
    set_flash('danger', "Customer not found.");
    header("Location: index.php");
    exit;
}

// Role check
if (!is_admin() && !empty($customer['created_by']) && (int)$customer['created_by'] !== (int)$current_user['id']) {
    set_flash('danger', "You do not have access to view this customer.");
    header("Location: index.php");
    exit;
}

// Fetch sales orders for this customer
$sales_query = "SELECT s.*, u.full_name as seller_name 
                FROM sales s 
                JOIN users u ON s.user_id = u.id 
                WHERE s.customer_id = ? 
                ORDER BY s.sale_date DESC";
$s_stmt = mysqli_prepare($conn, $sales_query);
mysqli_stmt_bind_param($s_stmt, "i", $id);
mysqli_stmt_execute($s_stmt);
$sales_res = mysqli_stmt_get_result($s_stmt);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Customer: <?php echo e($customer['customer_name']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Customers / Profile</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="edit.php?id=<?php echo e($customer['id']); ?>" class="btn btn-primary btn-sm">Edit Customer</a>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Customers</a>
    </div>
</div>

<div class="page-content">
    <div class="grid-row">
        <!-- Customer Info Card -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Contact Profile</span>
                    <span class="badge badge-info"><?php echo e($customer['customer_id']); ?></span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tr>
                            <th style="width: 150px;">Customer ID</th>
                            <td><strong><?php echo e($customer['customer_id']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Full Name</th>
                            <td><?php echo e($customer['customer_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><a href="tel:<?php echo e($customer['phone']); ?>" style="color:#2b5797; text-decoration:none;"><?php echo e($customer['phone']); ?></a></td>
                        </tr>
                        <tr>
                            <th>Email Address</th>
                            <td><?php echo e($customer['email'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Delivery Address</th>
                            <td><?php echo nl2br(e($customer['address'] ?? '—')); ?></td>
                        </tr>
                        <tr>
                            <th>Registered By</th>
                            <td>
                                <?php if (!empty($customer['created_by_name'])): ?>
                                    <?php echo e($customer['created_by_name']); ?> (<?php echo e($customer['creator_user_id']); ?>)
                                <?php else: ?>
                                    Direct / Walk-in
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Account Status</th>
                            <td>
                                <?php if ($customer['status'] === 'Active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Registration Date</th>
                            <td><?php echo date('d M Y, h:i A', strtotime($customer['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Sales Billing History -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Customer Invoices &amp; Purchases</span>
                    <span class="badge badge-info"><?php echo mysqli_num_rows($sales_res); ?> Invoice(s)</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (mysqli_num_rows($sales_res) === 0): ?>
                        <div style="padding: 25px; text-align: center; color: #888;">
                            No sales invoices recorded for this customer yet.
                        </div>
                    <?php else: ?>
                        <table class="data-table" style="margin-top: 0; border: none;">
                            <thead>
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Date</th>
                                    <th>Billed By</th>
                                    <th style="text-align: right;">Total Amount</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($inv = mysqli_fetch_assoc($sales_res)): ?>
                                    <tr>
                                        <td>
                                            <a href="../sales/view.php?id=<?php echo e($inv['id']); ?>" style="color:#2b5797; font-weight:bold; text-decoration:none;">
                                                <?php echo e($inv['sale_id']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($inv['sale_date'])); ?></td>
                                        <td><?php echo e($inv['seller_name']); ?></td>
                                        <td style="text-align: right; font-weight: bold; color: #2e7d32;">
                                            ₹<?php echo number_format($inv['total_amount'], 2); ?>
                                        </td>
                                        <td>
                                            <?php if ($inv['payment_status'] === 'Paid'): ?>
                                                <span class="badge badge-success">Paid</span>
                                            <?php elseif ($inv['payment_status'] === 'Partial'): ?>
                                                <span class="badge badge-warning">Partial</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Unpaid</span>
                                            <?php endif; ?>
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
if ($s_stmt) {
    mysqli_stmt_close($s_stmt);
}
include '../includes/footer.php';
?>
