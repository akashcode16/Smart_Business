<?php
/**
 * Stockiest Dashboard
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Strict Role Check - Only Stockiest allowed
require_role('Stockiest');

$current_user = get_logged_in_user();
$page_title = 'Stockiest Dashboard';
$active_menu = 'dashboard';
$user_id = (int)$current_user['id'];

// 1. Stock holdings
$stk_res = mysqli_query($conn, "SELECT COUNT(*) as skus, COALESCE(SUM(s.quantity), 0) as units, COALESCE(SUM(s.quantity * p.purchase_price), 0) as valuation 
                                FROM stock s JOIN products p ON s.product_id = p.id WHERE s.user_id = {$user_id}");
$stock_data = mysqli_fetch_assoc($stk_res);

// 2. Purchases from suppliers
$pur_res = mysqli_query($conn, "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as spent FROM purchases WHERE user_id = {$user_id}");
$purchases_data = mysqli_fetch_assoc($pur_res);

// 3. Outgoing transfers to distributors
$trf_res = mysqli_query($conn, "SELECT COUNT(*) as count, COALESCE(SUM(quantity), 0) as units FROM stock_transfers WHERE from_user_id = {$user_id}");
$transfer_data = mysqli_fetch_assoc($trf_res);

// 4. Low stock products
$low_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM stock s JOIN products p ON s.product_id = p.id WHERE s.user_id = {$user_id} AND s.quantity <= p.minimum_stock_level");
$low_stock_count = (int)(mysqli_fetch_assoc($low_res)['total'] ?? 0);

// 5. Recent Purchases
$recent_purchases = mysqli_query($conn, "SELECT p.*, s.supplier_name FROM purchases p JOIN suppliers s ON p.supplier_id = s.id WHERE p.user_id = {$user_id} ORDER BY p.id DESC LIMIT 5");

// 6. Recent Transfers Out
$recent_transfers = mysqli_query($conn, "SELECT st.*, p.product_name, p.unit, u.full_name as to_name 
                                         FROM stock_transfers st 
                                         JOIN products p ON st.product_id = p.id 
                                         JOIN users u ON st.to_user_id = u.id 
                                         WHERE st.from_user_id = {$user_id} 
                                         ORDER BY st.id DESC LIMIT 5");

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>Stockiest Dashboard</h1>
        <div class="page-breadcrumb">Smart Business / Stockiest / Dashboard</div>
    </div>
    <div>
        <a href="../purchases/create.php" class="btn btn-primary btn-sm">+ Make Supplier Purchase</a>
        <a href="../stock_transfers/create.php" class="btn btn-success btn-sm">+ Transfer to Distributor</a>
    </div>
</div>

<!-- Page Content -->
<div class="page-content">
    <!-- Live Metrics Cards -->
    <div class="stat-card-row">
        <div class="stat-card stat-green">
            <div class="stat-card-title">Warehouse Inventory</div>
            <div class="stat-card-value"><?php echo number_format($stock_data['units']); ?></div>
            <div class="stat-card-sub">Units on hand (Valuation: ₹<?php echo number_format($stock_data['valuation'], 2); ?>)</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-title">Supplier Purchases</div>
            <div class="stat-card-value">₹<?php echo number_format($purchases_data['spent'], 2); ?></div>
            <div class="stat-card-sub"><?php echo (int)$purchases_data['count']; ?> purchase orders recorded</div>
        </div>

        <div class="stat-card stat-orange">
            <div class="stat-card-title">Transfers to Distributors</div>
            <div class="stat-card-value"><?php echo number_format($transfer_data['units']); ?> units</div>
            <div class="stat-card-sub"><?php echo (int)$transfer_data['count']; ?> outbound dispatches completed</div>
        </div>

        <div class="stat-card <?php echo ($low_stock_count > 0) ? 'stat-red' : ''; ?>">
            <div class="stat-card-title">Low Stock Alerts</div>
            <div class="stat-card-value" style="<?php echo ($low_stock_count > 0) ? 'color: #c9302c;' : ''; ?>">
                <?php echo $low_stock_count; ?>
            </div>
            <div class="stat-card-sub"><a href="../stock/index.php?low_stock=1" style="color:#c9302c; text-decoration:none;">View low-stock SKUs &rarr;</a></div>
        </div>
    </div>

    <div class="grid-row">
        <!-- Recent Purchases -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Recent Supplier Purchases</span>
                    <a href="../purchases/index.php" style="font-size: 12px; color: #2b5797; text-decoration: none;">View All</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (mysqli_num_rows($recent_purchases) === 0): ?>
                        <div style="padding: 25px; text-align: center; color: #888;">
                            No purchase orders yet. <a href="../purchases/create.php">Create purchase entry</a>.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table" style="margin-top: 0; border: none;">
                                <thead>
                                    <tr>
                                        <th>PO #</th>
                                        <th>Supplier</th>
                                        <th>Date</th>
                                        <th style="text-align: right;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($po = mysqli_fetch_assoc($recent_purchases)): ?>
                                        <tr>
                                            <td><a href="../purchases/view.php?id=<?php echo e($po['id']); ?>" style="color:#1e40af; font-weight:600;"><?php echo e($po['purchase_id']); ?></a></td>
                                            <td><?php echo e($po['supplier_name']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($po['purchase_date'])); ?></td>
                                            <td style="text-align: right; font-weight: 700; color: #0f294a;">₹<?php echo number_format($po['total_amount'], 2); ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Transfers to Distributors -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Recent Dispatches to Distributors</span>
                    <a href="../stock_transfers/index.php" style="font-size: 12px; color: #1e40af; text-decoration: none; font-weight: 600;">View All &rarr;</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (mysqli_num_rows($recent_transfers) === 0): ?>
                        <div style="padding: 25px; text-align: center; color: #888;">
                            No outbound stock transfers yet. <a href="../stock_transfers/create.php">Dispatch stock</a>.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table" style="margin-top: 0; border: none;">
                                <thead>
                                    <tr>
                                        <th>Transfer #</th>
                                        <th>Distributor</th>
                                        <th>Product</th>
                                        <th style="text-align: right;">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($tr = mysqli_fetch_assoc($recent_transfers)): ?>
                                        <tr>
                                            <td><a href="../stock_transfers/view.php?id=<?php echo e($tr['id']); ?>" style="color:#1e40af; font-weight:600;"><?php echo e($tr['transfer_id']); ?></a></td>
                                            <td><?php echo e($tr['to_name']); ?></td>
                                            <td><?php echo e($tr['product_name']); ?></td>
                                            <td style="text-align: right; font-weight: 600; color: #15803d;">
                                                <?php echo number_format($tr['quantity']); ?> <?php echo e($tr['unit']); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
