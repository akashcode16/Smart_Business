<?php
/**
 * Retailer Dashboard
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Strict Role Check - Only Retailer allowed
require_role('Retailer');

$current_user = get_logged_in_user();
$page_title = 'Retailer Dashboard';
$active_menu = 'dashboard';
$user_id = (int)$current_user['id'];

// 1. Available Stock
$stk_res = mysqli_query($conn, "SELECT COUNT(*) as skus, COALESCE(SUM(s.quantity), 0) as units, COALESCE(SUM(s.quantity * p.selling_price), 0) as valuation 
                                FROM stock s JOIN products p ON s.product_id = p.id WHERE s.user_id = {$user_id}");
$stock_data = mysqli_fetch_assoc($stk_res);

// 2. Sales & Invoices
$sal_res = mysqli_query($conn, "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE user_id = {$user_id}");
$sales_data = mysqli_fetch_assoc($sal_res);

// 3. Liquid Collections
$pay_res = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as collected FROM payments WHERE user_id = {$user_id} AND transaction_type = 'Sale'");
$total_collected = (float)(mysqli_fetch_assoc($pay_res)['collected'] ?? 0);

// 4. Customers Registered
$cust_res = mysqli_query($conn, "SELECT COUNT(*) as count FROM customers WHERE created_by = {$user_id} OR created_by IS NULL");
$total_customers = (int)(mysqli_fetch_assoc($cust_res)['count'] ?? 0);

// 5. Low-stock products
$low_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM stock s JOIN products p ON s.product_id = p.id WHERE s.user_id = {$user_id} AND s.quantity <= p.minimum_stock_level");
$low_stock_count = (int)(mysqli_fetch_assoc($low_res)['total'] ?? 0);

// 6. Recent Sales Invoices
$recent_sales = mysqli_query($conn, "SELECT s.*, COALESCE(c.customer_name, 'Walk-in Retail') as customer_name 
                                    FROM sales s 
                                    LEFT JOIN customers c ON s.customer_id = c.id 
                                    WHERE s.user_id = {$user_id} 
                                    ORDER BY s.id DESC LIMIT 5");

// 7. Recent Inward Stock from Distributor
$recent_inward = mysqli_query($conn, "SELECT st.*, p.product_name, p.unit, u.full_name as from_distributor 
                                     FROM stock_transfers st 
                                     JOIN products p ON st.product_id = p.id 
                                     JOIN users u ON st.from_user_id = u.id 
                                     WHERE st.to_user_id = {$user_id} 
                                     ORDER BY st.id DESC LIMIT 5");

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>Retail Store Dashboard</h1>
        <div class="page-breadcrumb">Smart Business / Retailer / Dashboard</div>
    </div>
    <div>
        <a href="../sales/create.php" class="btn btn-primary btn-sm">+ New POS Bill / Sale</a>
        <a href="../customers/create.php" class="btn btn-secondary btn-sm">+ Register Customer</a>
    </div>
</div>

<!-- Page Content -->
<div class="page-content">
    <div class="stat-card-row">
        <div class="stat-card stat-purple">
            <div class="stat-card-title">Store Available Stock</div>
            <div class="stat-card-value"><?php echo number_format($stock_data['units']); ?></div>
            <div class="stat-card-sub"><?php echo (int)$stock_data['skus']; ?> product lines (Val: ₹<?php echo number_format($stock_data['valuation'], 2); ?>)</div>
        </div>

        <div class="stat-card stat-green">
            <div class="stat-card-title">Total Sales Revenue</div>
            <div class="stat-card-value">₹<?php echo number_format($sales_data['revenue'], 2); ?></div>
            <div class="stat-card-sub"><?php echo (int)$sales_data['count']; ?> retail invoices billed</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-title">Cash Collections</div>
            <div class="stat-card-value">₹<?php echo number_format($total_collected, 2); ?></div>
            <div class="stat-card-sub">Liquid collections settled</div>
        </div>

        <div class="stat-card <?php echo ($low_stock_count > 0) ? 'stat-red' : ''; ?>">
            <div class="stat-card-title">Low Stock Alerts</div>
            <div class="stat-card-value" style="<?php echo ($low_stock_count > 0) ? 'color: #c9302c;' : ''; ?>">
                <?php echo $low_stock_count; ?>
            </div>
            <div class="stat-card-sub"><a href="../stock/index.php?low_stock=1" style="color:#c9302c; text-decoration:none;">View shelf warnings &rarr;</a></div>
        </div>
    </div>

    <div class="grid-row">
        <!-- Recent Retail Sales -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Recent Counter Sales</span>
                    <a href="../sales/index.php" style="font-size: 12px; color: #2b5797; text-decoration: none;">View All</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (mysqli_num_rows($recent_sales) === 0): ?>
                        <div style="padding: 25px; text-align: center; color: #888;">
                            No retail bills generated yet. <a href="../sales/create.php">Open POS billing</a>.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table" style="margin-top: 0; border: none;">
                                <thead>
                                    <tr>
                                        <th>Bill #</th>
                                        <th>Customer</th>
                                        <th>Date</th>
                                        <th style="text-align: right;">Amount</th>
                                        <th style="text-align: center;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($s = mysqli_fetch_assoc($recent_sales)): ?>
                                        <tr>
                                            <td><a href="../sales/view.php?id=<?php echo e($s['id']); ?>" style="color:#1e40af; font-weight:600;"><?php echo e($s['sale_id']); ?></a></td>
                                            <td><?php echo e($s['customer_name']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($s['sale_date'])); ?></td>
                                            <td style="text-align: right; font-weight: 700; color: #0f294a;">₹<?php echo number_format($s['total_amount'], 2); ?></td>
                                            <td style="text-align: center;">
                                                <?php if ($s['payment_status'] === 'Paid'): ?>
                                                    <span class="badge badge-success">Paid</span>
                                                <?php elseif ($s['payment_status'] === 'Partial'): ?>
                                                    <span class="badge badge-warning">Partial</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Unpaid</span>
                                                <?php endif; ?>
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

        <!-- Recent Stock Received from Distributor -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Stock Received (From Distributor)</span>
                    <a href="../stock_transfers/index.php?direction=incoming" style="font-size: 12px; color: #1e40af; text-decoration: none; font-weight: 600;">View All &rarr;</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (mysqli_num_rows($recent_inward) === 0): ?>
                        <div style="padding: 25px; text-align: center; color: #888;">
                            No stock received yet. Transferred inventory from your distributor will be credited here.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table" style="margin-top: 0; border: none;">
                                <thead>
                                    <tr>
                                        <th>Challan #</th>
                                        <th>Distributor</th>
                                        <th>Product</th>
                                        <th style="text-align: right;">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($in = mysqli_fetch_assoc($recent_inward)): ?>
                                        <tr>
                                            <td><a href="../stock_transfers/view.php?id=<?php echo e($in['id']); ?>" style="color:#1e40af; font-weight:600;"><?php echo e($in['transfer_id']); ?></a></td>
                                            <td><?php echo e($in['from_distributor']); ?></td>
                                            <td><?php echo e($in['product_name']); ?></td>
                                            <td style="text-align: right; font-weight: 600; color: #15803d;">
                                                +<?php echo number_format($in['quantity']); ?> <?php echo e($in['unit']); ?>
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
