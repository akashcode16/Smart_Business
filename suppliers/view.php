<?php
/**
 * Supplier Management - View Supplier & Purchase History
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();
$page_title = 'Supplier Profile';
$active_menu = 'suppliers';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', "Invalid supplier specified.");
    header("Location: index.php");
    exit;
}

// Fetch supplier details
$stmt = mysqli_prepare($conn, "SELECT * FROM suppliers WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$supplier = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$supplier) {
    set_flash('danger', "Supplier not found.");
    header("Location: index.php");
    exit;
}

// Fetch purchases from this supplier
$purch_query = "SELECT p.*, u.full_name as purchaser_name 
                FROM purchases p 
                JOIN users u ON p.user_id = u.id 
                WHERE p.supplier_id = ? 
                ORDER BY p.purchase_date DESC";
$p_stmt = mysqli_prepare($conn, $purch_query);
mysqli_stmt_bind_param($p_stmt, "i", $id);
mysqli_stmt_execute($p_stmt);
$purchases_res = mysqli_stmt_get_result($p_stmt);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Supplier: <?php echo e($supplier['supplier_name']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Suppliers / Profile</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="edit.php?id=<?php echo e($supplier['id']); ?>" class="btn btn-primary btn-sm">Edit Supplier</a>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Suppliers</a>
    </div>
</div>

<div class="page-content">
    <div class="grid-row">
        <!-- Supplier Contact Info -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Vendor Profile</span>
                    <span class="badge badge-info"><?php echo e($supplier['supplier_id']); ?></span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tr>
                            <th style="width: 150px;">Supplier ID</th>
                            <td><strong><?php echo e($supplier['supplier_id']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Contact Person</th>
                            <td><?php echo e($supplier['supplier_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Company Name</th>
                            <td><?php echo e($supplier['company_name'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><a href="tel:<?php echo e($supplier['phone']); ?>" style="color:#2b5797; text-decoration:none;"><?php echo e($supplier['phone']); ?></a></td>
                        </tr>
                        <tr>
                            <th>Email Address</th>
                            <td><?php echo e($supplier['email'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td><?php echo nl2br(e($supplier['address'] ?? '—')); ?></td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <?php if ($supplier['status'] === 'Active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Registered On</th>
                            <td><?php echo date('d M Y', strtotime($supplier['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Purchase Orders History -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Purchase History</span>
                    <span class="badge badge-info"><?php echo mysqli_num_rows($purchases_res); ?> Order(s)</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (mysqli_num_rows($purchases_res) === 0): ?>
                        <div style="padding: 25px; text-align: center; color: #888;">
                            No purchase orders recorded with this supplier yet.
                        </div>
                    <?php else: ?>
                        <table class="data-table" style="margin-top: 0; border: none;">
                            <thead>
                                <tr>
                                    <th>Purchase #</th>
                                    <th>Date</th>
                                    <th>Purchaser</th>
                                    <th style="text-align: right;">Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($po = mysqli_fetch_assoc($purchases_res)): ?>
                                    <tr>
                                        <td>
                                            <a href="../purchases/view.php?id=<?php echo e($po['id']); ?>" style="color:#2b5797; font-weight:bold; text-decoration:none;">
                                                <?php echo e($po['purchase_id']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($po['purchase_date'])); ?></td>
                                        <td><?php echo e($po['purchaser_name']); ?></td>
                                        <td style="text-align: right; font-weight: bold;">₹<?php echo number_format($po['total_amount'], 2); ?></td>
                                        <td>
                                            <?php if ($po['payment_status'] === 'Paid'): ?>
                                                <span class="badge badge-success">Paid</span>
                                            <?php elseif ($po['payment_status'] === 'Partial'): ?>
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
if ($p_stmt) {
    mysqli_stmt_close($p_stmt);
}
include '../includes/footer.php';
?>
