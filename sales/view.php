<?php
/**
 * Sales Management - View Invoice Details
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Sales Invoice';
$active_menu = 'sales';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', "Invalid invoice specified.");
    header("Location: index.php");
    exit;
}

$query = "SELECT s.*, 
          COALESCE(c.customer_name, 'Walk-in Customer') as customer_name,
          c.phone as customer_phone, c.email as customer_email, c.address as customer_address,
          u.full_name as seller_name, u.user_id as seller_code, r.role_name as seller_role 
          FROM sales s 
          LEFT JOIN customers c ON s.customer_id = c.id 
          JOIN users u ON s.user_id = u.id 
          JOIN roles r ON u.role_id = r.id 
          WHERE s.id = ? LIMIT 1";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$sale = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$sale) {
    set_flash('danger', "Invoice not found.");
    header("Location: index.php");
    exit;
}

// Access check: non-admin can only see their own sales
if (!is_admin() && (int)$sale['user_id'] !== (int)$current_user['id']) {
    set_flash('danger', "You do not have access to view this invoice.");
    header("Location: index.php");
    exit;
}

// Fetch items
$items_query = "SELECT si.*, p.product_id as sku, p.product_name, p.unit, p.brand, c.category_name 
                FROM sale_items si 
                JOIN products p ON si.product_id = p.id 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE si.sale_id = ? 
                ORDER BY si.id ASC";
$i_stmt = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($i_stmt, "i", $id);
mysqli_stmt_execute($i_stmt);
$items_res = mysqli_stmt_get_result($i_stmt);

// Fetch payments
$pay_query = "SELECT * FROM payments WHERE transaction_type = 'Sale' AND reference_id = ? ORDER BY id ASC";
$pay_stmt = mysqli_prepare($conn, $pay_query);
mysqli_stmt_bind_param($pay_stmt, "s", $sale['sale_id']);
mysqli_stmt_execute($pay_stmt);
$payments_res = mysqli_stmt_get_result($pay_stmt);

$total_paid = 0.00;
$payments_list = [];
while ($p = mysqli_fetch_assoc($payments_res)) {
    $payments_list[] = $p;
    $total_paid += (float)$p['amount'];
}

$balance_due = max(0, (float)$sale['total_amount'] - $total_paid);

$flash_messages = get_flash();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Invoice: <?php echo e($sale['sale_id']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Sales / Invoice</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="print.php?id=<?php echo e($sale['id']); ?>" target="_blank" class="btn btn-secondary btn-sm">&#128438; Print Customer Invoice</a>
        <?php if ($balance_due > 0): ?>
            <a href="record_payment.php?id=<?php echo e($sale['id']); ?>" class="btn btn-warning btn-sm">+ Settle / Pay Balance</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Sales</a>
    </div>
</div>

<div class="page-content">
    <?php foreach ($flash_messages as $flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endforeach; ?>

    <div class="grid-row">
        <!-- Customer & Seller Details -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Customer Details</span>
                    <span class="badge badge-info"><?php echo e($sale['customer_name']); ?></span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tr>
                            <th style="width: 140px;">Customer Name</th>
                            <td><strong><?php echo e($sale['customer_name']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Contact Phone</th>
                            <td><?php echo e($sale['customer_phone'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Email Address</th>
                            <td><?php echo e($sale['customer_email'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td><?php echo nl2br(e($sale['customer_address'] ?? 'Counter Sales Walk-in')); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Invoice Summary & Status -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Invoice Particulars</span>
                    <span class="badge-role badge-retailer"><?php echo e($sale['payment_method']); ?></span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tr>
                            <th style="width: 140px;">Invoice #</th>
                            <td><strong><?php echo e($sale['sale_id']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Invoice Date</th>
                            <td><?php echo date('d F Y', strtotime($sale['sale_date'])); ?></td>
                        </tr>
                        <tr>
                            <th>Billed By</th>
                            <td><?php echo e($sale['seller_name']); ?> (<?php echo e($sale['seller_role']); ?>)</td>
                        </tr>
                        <tr>
                            <th>Payment Status</th>
                            <td>
                                <?php if ($balance_due <= 0): ?>
                                    <span class="badge badge-success">&check; Fully Paid</span>
                                <?php elseif ($total_paid > 0): ?>
                                    <span class="badge badge-warning">Partial (Balance: ₹<?php echo number_format($balance_due, 2); ?>)</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Unpaid</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Grand Total</th>
                            <td><strong style="font-size: 16px; color: #1a365d;">₹<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Itemized Table -->
    <div class="card">
        <div class="card-header">
            <span>Billed Items</span>
            <span class="badge badge-info"><?php echo mysqli_num_rows($items_res); ?> Item(s)</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <table class="data-table" style="margin-top: 0; border: none;">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="width: 110px;">SKU</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th style="text-align: right; width: 130px;">Unit Price (₹)</th>
                        <th style="text-align: right; width: 110px;">Quantity</th>
                        <th style="text-align: right; width: 140px;">Line Total (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $line = 1;
                    $total_qty = 0;
                    while ($it = mysqli_fetch_assoc($items_res)): 
                        $total_qty += (int)$it['quantity'];
                    ?>
                        <tr>
                            <td><?php echo $line++; ?></td>
                            <td><strong><?php echo e($it['sku']); ?></strong></td>
                            <td>
                                <strong><?php echo e($it['product_name']); ?></strong>
                                <?php if (!empty($it['brand'])): ?>
                                    <span style="font-size: 11px; color: #777;">&bull; <?php echo e($it['brand']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($it['category_name'] ?? '—'); ?></td>
                            <td style="text-align: right;">₹<?php echo number_format($it['selling_price'], 2); ?></td>
                            <td style="text-align: right; font-weight: bold;"><?php echo number_format($it['quantity']); ?> <?php echo e($it['unit']); ?></td>
                            <td style="text-align: right; font-weight: bold; color: #2e7d32;">₹<?php echo number_format($it['subtotal'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #fafbfc; font-size: 14px;">
                        <th colspan="5" style="text-align: right;">Invoice Grand Total:</th>
                        <th style="text-align: right;"><?php echo number_format($total_qty); ?></th>
                        <th style="text-align: right; font-size: 16px; color: #1a365d;">₹<?php echo number_format($sale['total_amount'], 2); ?></th>
                    </tr>
                    <tr style="background: #fafbfc;">
                        <th colspan="6" style="text-align: right;">Total Amount Received:</th>
                        <th style="text-align: right; color: #2e7d32; font-size: 15px;">₹<?php echo number_format($total_paid, 2); ?></th>
                    </tr>
                    <tr style="background: #fafbfc;">
                        <th colspan="6" style="text-align: right;">Outstanding Balance Due:</th>
                        <th style="text-align: right; <?php echo ($balance_due > 0) ? 'color: #c9302c; font-size: 16px;' : 'color: #2e7d32;'; ?>">
                            ₹<?php echo number_format($balance_due, 2); ?>
                        </th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- Payments History Card -->
    <div class="card">
        <div class="card-header">
            <span>Payment Receipts</span>
            <?php if ($balance_due > 0): ?>
                <a href="record_payment.php?id=<?php echo e($sale['id']); ?>" class="btn btn-warning btn-sm">+ Record Payment</a>
            <?php endif; ?>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($payments_list)): ?>
                <div style="padding: 20px; text-align: center; color: #888;">
                    No payment recorded yet for this invoice.
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Date</th>
                            <th>Payment Method</th>
                            <th style="text-align: right;">Amount (₹)</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments_list as $pay): ?>
                            <tr>
                                <td><strong><?php echo e($pay['payment_id']); ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($pay['payment_date'])); ?></td>
                                <td><span class="badge badge-info"><?php echo e($pay['payment_method']); ?></span></td>
                                <td style="text-align: right; font-weight: bold; color: #2e7d32;">₹<?php echo number_format($pay['amount'], 2); ?></td>
                                <td><?php echo e($pay['notes'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if ($i_stmt) mysqli_stmt_close($i_stmt);
if ($pay_stmt) mysqli_stmt_close($pay_stmt);
include '../includes/footer.php';
?>
