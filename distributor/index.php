<?php
/**
 * Distributor Dashboard
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Strict Role Check - Only Distributor allowed
require_role('Distributor');

$current_user = get_logged_in_user();
$page_title = 'Distributor Dashboard';
$active_menu = 'dashboard';
$user_id = (int)$current_user['id'];

// 1. Available Inventory
$stk_res = mysqli_query($conn, "SELECT COUNT(*) as skus, COALESCE(SUM(s.quantity), 0) as units, COALESCE(SUM(s.quantity * p.purchase_price), 0) as valuation 
                                FROM stock s JOIN products p ON s.product_id = p.id WHERE s.user_id = {$user_id}");
$stock_data = mysqli_fetch_assoc($stk_res);

// 2. Received stock from Stockiest
$rec_res = mysqli_query($conn, "SELECT COUNT(*) as count, COALESCE(SUM(quantity), 0) as units FROM stock_transfers WHERE to_user_id = {$user_id}");
$received_data = mysqli_fetch_assoc($rec_res);

// 3. Outgoing transfers to Retailers
$out_res = mysqli_query($conn, "SELECT COUNT(*) as count, COALESCE(SUM(quantity), 0) as units FROM stock_transfers WHERE from_user_id = {$user_id}");
$dispatched_data = mysqli_fetch_assoc($out_res);

// 4. Sales / Wholesale Revenue
$sal_res = mysqli_query($conn, "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM sales WHERE user_id = {$user_id}");
$sales_data = mysqli_fetch_assoc($sal_res);

// 5. Recent Inward Stock Transfers
$recent_inward = mysqli_query($conn, "SELECT st.*, p.product_name, p.unit, u.full_name as from_name 
                                     FROM stock_transfers st 
                                     JOIN products p ON st.product_id = p.id 
                                     JOIN users u ON st.from_user_id = u.id 
                                     WHERE st.to_user_id = {$user_id} 
                                     ORDER BY st.id DESC LIMIT 5");

// 6. Recent Outward Transfers to Retailers
$recent_outward = mysqli_query($conn, "SELECT st.*, p.product_name, p.unit, u.full_name as to_name 
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
        <h1>Distributor Dashboard</h1>
        <div class="page-breadcrumb">Smart Business / Distributor / Dashboard</div>
    </div>
    <div>
        <a href="../stock_transfers/create.php" class="btn btn-primary btn-sm">+ Dispatch Stock to Retailer</a>
        <a href="../sales/create.php" class="btn btn-secondary btn-sm">+ Generate Sale Invoice</a>
    </div>
</div>

<!-- Page Content -->
<div class="page-content">
    <div class="stat-card-row">
        <div class="stat-card stat-orange">
            <div class="stat-card-title">Hub Available Stock</div>
            <div class="stat-card-value"><?php echo number_format($stock_data['units']); ?></div>
            <div class="stat-card-sub">In stock (Valuation: ₹<?php echo number_format($stock_data['valuation'], 2); ?>)</div>
        </div>

        <div class="stat-card stat-green">
            <div class="stat-card-title">Received from Stockiest</div>
            <div class="stat-card-value"><?php echo number_format($received_data['units']); ?> units</div>
            <div class="stat-card-sub"><?php echo (int)$received_data['count']; ?> inward dispatches received</div>
        </div>

        <div class="stat-card stat-purple">
            <div class="stat-card-title">Transferred to Retailers</div>
            <div class="stat-card-value"><?php echo number_format($dispatched_data['units']); ?> units</div>
            <div class="stat-card-sub"><?php echo (int)$dispatched_data['count']; ?> store supply dispatches</div>
        </div>

        <div class="stat-card">
            <div class="stat-card-title">Direct Sales Revenue</div>
            <div class="stat-card-value">₹<?php echo number_format($sales_data['revenue'], 2); ?></div>
            <div class="stat-card-sub"><?php echo (int)$sales_data['count']; ?> billing orders processed</div>
        </div>
    </div>

    <div class="grid-row">
        <!-- Inward Transfers from Stockiest -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Recent Inward Stock (From Stockiest)</span>
                    <a href="../stock_transfers/index.php?direction=incoming" style="font-size: 12px; color: #2b5797; text-decoration: none;">View Inward</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (mysqli_num_rows($recent_inward) === 0): ?>
                        <div style="padding: 25px; text-align: center; color: #888;">
                            No incoming stock transfers received yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table" style="margin-top: 0; border: none;">
                                <thead>
                                    <tr>
                                        <th>Challan #</th>
                                        <th>From Stockiest</th>
                                        <th>Product</th>
                                        <th style="text-align: right;">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($in = mysqli_fetch_assoc($recent_inward)): ?>
                                        <tr>
                                            <td><a href="../stock_transfers/view.php?id=<?php echo e($in['id']); ?>" style="color:#1e40af; font-weight:600;"><?php echo e($in['transfer_id']); ?></a></td>
                                            <td><?php echo e($in['from_name']); ?></td>
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

        <!-- Outward Transfers to Retailers -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Recent Outward Transfers (To Retailers)</span>
                    <a href="../stock_transfers/index.php?direction=outgoing" style="font-size: 12px; color: #1e40af; text-decoration: none; font-weight: 600;">View Outward &rarr;</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (mysqli_num_rows($recent_outward) === 0): ?>
                        <div style="padding: 25px; text-align: center; color: #888;">
                            No retail transfers dispatched yet. <a href="../stock_transfers/create.php">Dispatch stock</a>.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table" style="margin-top: 0; border: none;">
                                <thead>
                                    <tr>
                                        <th>Challan #</th>
                                        <th>To Retailer</th>
                                        <th>Product</th>
                                        <th style="text-align: right;">Quantity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($out = mysqli_fetch_assoc($recent_outward)): ?>
                                        <tr>
                                            <td><a href="../stock_transfers/view.php?id=<?php echo e($out['id']); ?>" style="color:#1e40af; font-weight:600;"><?php echo e($out['transfer_id']); ?></a></td>
                                            <td><?php echo e($out['to_name']); ?></td>
                                            <td><?php echo e($out['product_name']); ?></td>
                                            <td style="text-align: right; font-weight: 600; color: #dc2626;">
                                                -<?php echo number_format($out['quantity']); ?> <?php echo e($out['unit']); ?>
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
