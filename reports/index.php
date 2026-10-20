<?php
/**
 * Reports & Analytics - Central Report Hub
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Business Reports';
$active_menu = 'reports';

$user_id = (int)$current_user['id'];
$user_role = $current_user['role_name'];

// Real MySQL Queries based on role
// 1. Stock holdings
if (is_admin()) {
    $stk_res = mysqli_query($conn, "SELECT COUNT(*) as total_items, COALESCE(SUM(s.quantity), 0) as total_units, COALESCE(SUM(s.quantity * p.purchase_price), 0) as total_val 
                                    FROM stock s JOIN products p ON s.product_id = p.id");
} else {
    $stk_res = mysqli_query($conn, "SELECT COUNT(*) as total_items, COALESCE(SUM(s.quantity), 0) as total_units, COALESCE(SUM(s.quantity * p.purchase_price), 0) as total_val 
                                    FROM stock s JOIN products p ON s.product_id = p.id WHERE s.user_id = {$user_id}");
}
$stock_stat = mysqli_fetch_assoc($stk_res);

// 2. Sales
if (is_admin()) {
    $sale_res = mysqli_query($conn, "SELECT COUNT(*) as total_invoices, COALESCE(SUM(total_amount), 0) as total_rev FROM sales");
} else {
    $sale_res = mysqli_query($conn, "SELECT COUNT(*) as total_invoices, COALESCE(SUM(total_amount), 0) as total_rev FROM sales WHERE user_id = {$user_id}");
}
$sale_stat = mysqli_fetch_assoc($sale_res);

// 3. Purchases
if (is_admin() || is_stockiest()) {
    if (is_admin()) {
        $pur_res = mysqli_query($conn, "SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_spent FROM purchases");
    } else {
        $pur_res = mysqli_query($conn, "SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_spent FROM purchases WHERE user_id = {$user_id}");
    }
    $pur_stat = mysqli_fetch_assoc($pur_res);
}

// 4. Transfers
if (is_admin()) {
    $trf_res = mysqli_query($conn, "SELECT COUNT(*) as total_transfers, COALESCE(SUM(quantity), 0) as total_trans_units FROM stock_transfers");
} else {
    $trf_res = mysqli_query($conn, "SELECT COUNT(*) as total_transfers, COALESCE(SUM(quantity), 0) as total_trans_units FROM stock_transfers WHERE from_user_id = {$user_id} OR to_user_id = {$user_id}");
}
$trf_stat = mysqli_fetch_assoc($trf_res);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Business Intelligence &amp; Reports</h1>
        <div class="page-breadcrumb">Smart Business / Reports</div>
    </div>
</div>

<div class="page-content">
    <!-- Stat Cards Overview -->
    <div class="stat-card-row">
        <div class="stat-card stat-green">
            <div class="stat-card-title">Stock Valuation</div>
            <div class="stat-card-value">₹<?php echo number_format($stock_stat['total_val'], 2); ?></div>
            <div class="stat-card-sub"><?php echo number_format($stock_stat['total_units']); ?> units across <?php echo $stock_stat['total_items']; ?> SKUs</div>
        </div>

        <?php if (is_admin() || is_stockiest()): ?>
            <div class="stat-card">
                <div class="stat-card-title">Purchases Inward</div>
                <div class="stat-card-value">₹<?php echo number_format($pur_stat['total_spent'], 2); ?></div>
                <div class="stat-card-sub"><?php echo (int)$pur_stat['total_orders']; ?> purchase orders recorded</div>
            </div>
        <?php endif; ?>

        <?php if (is_admin() || is_stockiest() || is_distributor()): ?>
            <div class="stat-card stat-orange">
                <div class="stat-card-title">Stock Transfers</div>
                <div class="stat-card-value"><?php echo number_format($trf_stat['total_trans_units']); ?> units</div>
                <div class="stat-card-sub"><?php echo (int)$trf_stat['total_transfers']; ?> distribution dispatch operations</div>
            </div>
        <?php endif; ?>

        <div class="stat-card stat-purple">
            <div class="stat-card-title">Sales Revenue</div>
            <div class="stat-card-value">₹<?php echo number_format($sale_stat['total_rev'], 2); ?></div>
            <div class="stat-card-sub"><?php echo (int)$sale_stat['total_invoices']; ?> sales invoices generated</div>
        </div>
    </div>

    <!-- Reports Directory Grid -->
    <div class="card">
        <div class="card-header">
            <span>Operational &amp; Financial Reports Directory</span>
            <span class="badge badge-info"><?php echo e($user_role); ?> Access</span>
        </div>
        <div class="card-body">
            <p style="color: #666; margin-bottom: 16px;">
                Access detailed reporting modules with custom date range filters, entity drill-downs and tabular data export:
            </p>

            <div class="module-list-grid">
                <a href="stock.php" class="module-card-item">
                    <h4>&bull; Stock &amp; Inventory Report</h4>
                    <p>Current stock levels, inventory valuations and low-stock alert thresholds.</p>
                </a>

                <?php if (is_admin() || is_stockiest()): ?>
                    <a href="purchases.php" class="module-card-item">
                        <h4>&bull; Purchase Summary Report</h4>
                        <p>Supplier purchase records, cost analytics, payments and delivery schedules.</p>
                    </a>
                <?php endif; ?>

                <?php if (is_admin() || is_stockiest() || is_distributor()): ?>
                    <a href="transfers.php" class="module-card-item">
                        <h4>&bull; Stock Transfer Report</h4>
                        <p>Supply chain movement tracking: Stockiest &rarr; Distributor &rarr; Retailer.</p>
                    </a>
                <?php endif; ?>

                <?php if (is_admin() || is_distributor() || is_retailer()): ?>
                    <a href="sales.php" class="module-card-item">
                        <h4>&bull; Sales &amp; Revenue Report</h4>
                        <p>Billing totals, revenue breakdowns, product velocities and profit margins.</p>
                    </a>

                    <a href="customers.php" class="module-card-item">
                        <h4>&bull; Customer Spending Report</h4>
                        <p>Customer ledger balances, lifetime purchase volumes and account histories.</p>
                    </a>
                <?php endif; ?>

                <a href="payments.php" class="module-card-item">
                    <h4>&bull; Payment Ledger Report</h4>
                    <p>Cash, UPI, Card and Bank Transfer collections and settlements.</p>
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
