<?php
/**
 * Purchase Management - View Purchase Order Details
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();
$page_title = 'Purchase Order Details';
$active_menu = 'purchases';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', "Invalid purchase specified.");
    header("Location: index.php");
    exit;
}

// Fetch purchase
$query = "SELECT p.*, s.supplier_id as vendor_code, s.supplier_name, s.company_name, s.phone as supplier_phone, s.email as supplier_email, s.address as supplier_address,
          u.full_name as purchaser_name, u.user_id as purchaser_code 
          FROM purchases p 
          JOIN suppliers s ON p.supplier_id = s.id 
          JOIN users u ON p.user_id = u.id 
          WHERE p.id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$purchase = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$purchase) {
    set_flash('danger', "Purchase order not found.");
    header("Location: index.php");
    exit;
}

// Role restriction: Non-admin can only view their own purchases
if (!is_admin() && (int)$purchase['user_id'] !== (int)$current_user['id']) {
    set_flash('danger', "You do not have permission to view this purchase order.");
    header("Location: index.php");
    exit;
}

// Fetch items
$items_query = "SELECT pi.*, p.product_id as sku, p.product_name, p.unit, p.brand, c.category_name 
                FROM purchase_items pi 
                JOIN products p ON pi.product_id = p.id 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE pi.purchase_id = ? 
                ORDER BY pi.id ASC";
$i_stmt = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($i_stmt, "i", $id);
mysqli_stmt_execute($i_stmt);
$items_result = mysqli_stmt_get_result($i_stmt);

// Fetch payment records if any
$pay_query = "SELECT * FROM payments WHERE transaction_type = 'Purchase' AND reference_id = ? ORDER BY id ASC";
$pay_stmt = mysqli_prepare($conn, $pay_query);
mysqli_stmt_bind_param($pay_stmt, "s", $purchase['purchase_id']);
mysqli_stmt_execute($pay_stmt);
$payments_result = mysqli_stmt_get_result($pay_stmt);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Purchase Order: <?php echo e($purchase['purchase_id']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Purchases / Details</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="print.php?id=<?php echo e($purchase['id']); ?>" target="_blank" class="btn btn-secondary btn-sm">&#128438; Print Purchase Order</a>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Purchases</a>
    </div>
</div>

<div class="page-content">
    <div class="grid-row">
        <!-- Order & Vendor Info -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Vendor &amp; Supplier Profile</span>
                    <span class="badge badge-info"><?php echo e($purchase['vendor_code']); ?></span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tr>
                            <th style="width: 140px;">Supplier</th>
                            <td>
                                <strong><?php echo e($purchase['supplier_name']); ?></strong>
                                <?php if (!empty($purchase['company_name'])): ?>
                                    <div style="color: #666; font-size: 12px;"><?php echo e($purchase['company_name']); ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><?php echo e($purchase['supplier_phone']); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo e($purchase['supplier_email'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td><?php echo nl2br(e($purchase['supplier_address'] ?? '—')); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- PO Summary -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Order Particulars</span>
                    <span class="badge-role badge-admin"><?php echo e($purchase['payment_status']); ?></span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tr>
                            <th style="width: 140px;">Purchase Number</th>
                            <td><strong><?php echo e($purchase['purchase_id']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Purchase Date</th>
                            <td><?php echo date('d F Y', strtotime($purchase['purchase_date'])); ?></td>
                        </tr>
                        <tr>
                            <th>Purchaser / Account</th>
                            <td><?php echo e($purchase['purchaser_name']); ?> (<?php echo e($purchase['purchaser_code']); ?>)</td>
                        </tr>
                        <tr>
                            <th>Payment Status</th>
                            <td>
                                <?php if ($purchase['payment_status'] === 'Paid'): ?>
                                    <span class="badge badge-success">Settled &bull; Paid</span>
                                <?php elseif ($purchase['payment_status'] === 'Partial'): ?>
                                    <span class="badge badge-warning">Partial</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Unpaid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Total Amount</th>
                            <td><strong style="color: #1a365d; font-size: 16px;">₹<?php echo number_format($purchase['total_amount'], 2); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table -->
    <div class="card">
        <div class="card-header">
            <span>Itemized Inward Products</span>
            <span class="badge badge-info"><?php echo mysqli_num_rows($items_result); ?> Line Item(s)</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table" style="margin-top: 0; border: none;">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="width: 110px;">SKU</th>
                        <th>Product Description</th>
                        <th>Category</th>
                        <th style="text-align: right; width: 130px;">Unit Cost</th>
                        <th style="text-align: right; width: 110px;">Quantity</th>
                        <th style="text-align: right; width: 140px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $line_idx = 1;
                    $calc_units = 0;
                    while ($it = mysqli_fetch_assoc($items_result)): 
                        $calc_units += (int)$it['quantity'];
                    ?>
                        <tr>
                            <td><?php echo $line_idx++; ?></td>
                            <td><strong><?php echo e($it['sku']); ?></strong></td>
                            <td>
                                <strong><?php echo e($it['product_name']); ?></strong>
                                <?php if (!empty($it['brand'])): ?>
                                    <span style="font-size: 11px; color: #777;">&bull; Brand: <?php echo e($it['brand']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($it['category_name'] ?? '—'); ?></td>
                            <td style="text-align: right;">₹<?php echo number_format($it['purchase_price'], 2); ?></td>
                            <td style="text-align: right; font-weight: bold;"><?php echo number_format($it['quantity']); ?> <?php echo e($it['unit']); ?></td>
                            <td style="text-align: right; font-weight: bold; color: #1a365d;">₹<?php echo number_format($it['subtotal'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #fafbfc; font-size: 14px;">
                        <th colspan="5" style="text-align: right;">Grand Total:</th>
                        <th style="text-align: right;"><?php echo number_format($calc_units); ?> units</th>
                        <th style="text-align: right; color: #1a365d; font-size: 16px;">
                            ₹<?php echo number_format($purchase['total_amount'], 2); ?>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php if (!empty($purchase['notes'])): ?>
        <div class="card">
            <div class="card-header">
                <span>Purchase Notes</span>
            </div>
            <div class="card-body">
                <p><?php echo nl2br(e($purchase['notes'])); ?></p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (mysqli_num_rows($payments_result) > 0): ?>
        <div class="card">
            <div class="card-header">
                <span>Settlement Payment Entries</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Date</th>
                            <th>Method</th>
                            <th style="text-align: right;">Amount Paid</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($pay = mysqli_fetch_assoc($payments_result)): ?>
                            <tr>
                                <td><strong><?php echo e($pay['payment_id']); ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($pay['payment_date'])); ?></td>
                                <td><span class="badge badge-info"><?php echo e($pay['payment_method']); ?></span></td>
                                <td style="text-align: right; font-weight: bold; color: #2e7d32;">₹<?php echo number_format($pay['amount'], 2); ?></td>
                                <td><?php echo e($pay['notes'] ?? '—'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
if ($i_stmt) mysqli_stmt_close($i_stmt);
if ($pay_stmt) mysqli_stmt_close($pay_stmt);
include '../includes/footer.php';
?>
