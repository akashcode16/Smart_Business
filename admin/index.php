<?php
/**
 * Admin Dashboard
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Strict Role Check - Only Admin allowed
require_role('Admin');

$current_user = get_logged_in_user();
$page_title = 'Admin Dashboard';
$active_menu = 'dashboard';

// Fetch Live Database Metrics
// 1. Total Active Users
$u_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE status = 'Active'");
$total_active_users = (int)(mysqli_fetch_assoc($u_res)['total'] ?? 0);

// 2. Total Products
$p_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM products WHERE status = 'Active'");
$total_products = (int)(mysqli_fetch_assoc($p_res)['total'] ?? 0);

// 3. Total Categories
$c_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM categories WHERE status = 'Active'");
$total_categories = (int)(mysqli_fetch_assoc($c_res)['total'] ?? 0);

// 4. Sales Summary
$s_res = mysqli_query($conn, "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue FROM sales");
$sales_data = mysqli_fetch_assoc($s_res);
$total_sales_count = (int)($sales_data['count'] ?? 0);
$total_sales_revenue = (float)($sales_data['revenue'] ?? 0);

// 5. Purchases Summary
$pur_res = mysqli_query($conn, "SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as expenditure FROM purchases");
$purchases_data = mysqli_fetch_assoc($pur_res);
$total_purchases_count = (int)($purchases_data['count'] ?? 0);
$total_purchases_spent = (float)($purchases_data['expenditure'] ?? 0);

// 6. Low-Stock Products count across inventory
$low_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM stock s JOIN products p ON s.product_id = p.id WHERE s.quantity <= p.minimum_stock_level");
$total_low_stock = (int)(mysqli_fetch_assoc($low_res)['total'] ?? 0);

// 7. Recent Transactions (Recent Sales & Transfers)
$recent_sales_res = mysqli_query($conn, "SELECT s.sale_id, s.sale_date, s.total_amount, s.payment_status, 
                                        COALESCE(c.customer_name, 'Walk-in') as customer_name, u.full_name as seller_name 
                                        FROM sales s 
                                        LEFT JOIN customers c ON s.customer_id = c.id 
                                        JOIN users u ON s.user_id = u.id 
                                        ORDER BY s.id DESC LIMIT 5");

$recent_transfers_res = mysqli_query($conn, "SELECT st.transfer_id, st.transfer_date, st.quantity, p.product_name, p.unit,
                                            u_from.full_name as from_name, u_to.full_name as to_name 
                                            FROM stock_transfers st 
                                            JOIN products p ON st.product_id = p.id 
                                            JOIN users u_from ON st.from_user_id = u_from.id 
                                            JOIN users u_to ON st.to_user_id = u_to.id 
                                            ORDER BY st.id DESC LIMIT 5");

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>Admin Dashboard</h1>
        <div class="page-breadcrumb">Smart Business / Admin / Dashboard</div>
    </div>
    <div>
        <span class="badge badge-success" style="font-size: 12px; padding: 5px 10px;">System Online &bull; MySQL Connected</span>
    </div>
</div>

<!-- Page Content -->
<div class="page-content">
    <!-- Real Live Stat Cards -->
    <div class="stat-card-row">
        <div class="stat-card">
            <div class="stat-card-title">Active Users</div>
            <div class="stat-card-value"><?php echo $total_active_users; ?></div>
            <div class="stat-card-sub"><a href="../users/index.php" style="color: #1e40af; text-decoration: none; font-weight: 500;">Manage Accounts &rarr;</a></div>
        </div>

        <div class="stat-card stat-green">
            <div class="stat-card-title">Catalog Products</div>
            <div class="stat-card-value"><?php echo $total_products; ?></div>
            <div class="stat-card-sub"><?php echo $total_categories; ?> active categories</div>
        </div>

        <div class="stat-card stat-purple">
            <div class="stat-card-title">Network Sales Revenue</div>
            <div class="stat-card-value">₹<?php echo number_format($total_sales_revenue, 2); ?></div>
            <div class="stat-card-sub"><?php echo $total_sales_count; ?> total invoices</div>
        </div>

        <div class="stat-card stat-orange">
            <div class="stat-card-title">Supplier Purchases</div>
            <div class="stat-card-value">₹<?php echo number_format($total_purchases_spent, 2); ?></div>
            <div class="stat-card-sub"><?php echo $total_purchases_count; ?> purchase orders</div>
        </div>

        <div class="stat-card <?php echo ($total_low_stock > 0) ? 'stat-red' : ''; ?>">
            <div class="stat-card-title">Low Stock Warnings</div>
            <div class="stat-card-value" style="<?php echo ($total_low_stock > 0) ? 'color: #dc2626;' : ''; ?>">
                <?php echo $total_low_stock; ?>
            </div>
            <div class="stat-card-sub"><a href="../stock/index.php?low_stock=1" style="color: #dc2626; text-decoration: none; font-weight: 500;">View alerts &rarr;</a></div>
        </div>
    </div>

    <div class="grid-row">
        <!-- Authorized Session Details Card -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Authorized Session Details</span>
                    <span class="badge badge-success">Active &amp; Authorized</span>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="data-table" style="margin-top: 0; border: none;">
                            <tbody>
                                <tr>
                                    <th style="width: 170px; background-color: #f8fafc;">User ID</th>
                                    <td><strong><?php echo htmlspecialchars($current_user['user_id']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8fafc;">Full Name</th>
                                    <td><?php echo htmlspecialchars($current_user['full_name']); ?></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8fafc;">Assigned Role</th>
                                    <td><span class="badge badge-role badge-admin"><?php echo htmlspecialchars($current_user['role_name']); ?></span></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8fafc;">Database Record ID</th>
                                    <td>#<?php echo htmlspecialchars($current_user['id']); ?></td>
                                </tr>
                                <tr>
                                    <th style="background-color: #f8fafc;">Access Clearance</th>
                                    <td>Full Enterprise Administration</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Supply Chain Transfers -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Recent Supply Chain Transfers</span>
                    <a href="../stock_transfers/index.php" style="font-size: 12px; color: #1e40af; text-decoration: none; font-weight: 600;">View All &rarr;</a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (mysqli_num_rows($recent_transfers_res) === 0): ?>
                        <div style="padding: 25px; text-align: center; color: #64748b;">
                            No stock transfers recorded yet.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table" style="margin-top: 0; border: none;">
                                <thead>
                                    <tr>
                                        <th style="width: 115px;">Transfer #</th>
                                        <th>From &rarr; To</th>
                                        <th>Product</th>
                                        <th style="text-align: right; width: 100px;">Qty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($tr = mysqli_fetch_assoc($recent_transfers_res)): ?>
                                        <tr>
                                            <td>
                                                <a href="../stock_transfers/view.php?id=<?php echo e($tr['transfer_id']); ?>" style="color: #1e40af; font-weight: 600; text-decoration: none;">
                                                    <?php echo e($tr['transfer_id']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php echo e($tr['from_name']); ?> &rarr; <?php echo e($tr['to_name']); ?>
                                            </td>
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

    <!-- Recent Sales Transactions Card -->
    <div class="card">
        <div class="card-header">
            <span>Recent Network Sales Invoices</span>
            <a href="../sales/index.php" style="font-size: 12px; color: #1e40af; text-decoration: none; font-weight: 600;">View All Invoices &rarr;</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (mysqli_num_rows($recent_sales_res) === 0): ?>
                <div style="padding: 25px; text-align: center; color: #64748b;">
                    No sales recorded yet.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table" style="margin-top: 0; border: none;">
                        <thead>
                            <tr>
                                <th style="width: 130px;">Invoice #</th>
                                <th style="width: 120px;">Date</th>
                                <th>Customer</th>
                                <th>Billed By</th>
                                <th style="text-align: right; width: 140px;">Amount</th>
                                <th style="width: 110px; text-align: center;">Payment</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($s = mysqli_fetch_assoc($recent_sales_res)): ?>
                                <tr>
                                    <td>
                                        <a href="../sales/view.php?id=<?php echo e($s['sale_id']); ?>" style="color: #1e40af; font-weight: 600; text-decoration: none;">
                                            <?php echo e($s['sale_id']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($s['sale_date'])); ?></td>
                                    <td><?php echo e($s['customer_name']); ?></td>
                                    <td><?php echo e($s['seller_name']); ?></td>
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

    <!-- Administrative Modules Quick Navigation -->
    <div class="card">
        <div class="card-header">
            <span>Administrative Modules</span>
            <span style="font-size: 12px; font-weight: normal; color: #666;">Quick Access Links</span>
        </div>
        <div class="card-body">
            <div class="module-list-grid">
                <a href="../users/index.php" class="module-card-item">
                    <h4>User Management</h4>
                    <p>Manage users, credentials and role assignments.</p>
                </a>
                <a href="../categories/index.php" class="module-card-item">
                    <h4>Categories</h4>
                    <p>Manage product classifications and categories.</p>
                </a>
                <a href="../products/index.php" class="module-card-item">
                    <h4>Products</h4>
                    <p>Product catalog, pricing, brands and units.</p>
                </a>
                <a href="../suppliers/index.php" class="module-card-item">
                    <h4>Suppliers</h4>
                    <p>Vendor directory, contact details and profiles.</p>
                </a>
                <a href="../customers/index.php" class="module-card-item">
                    <h4>Customers</h4>
                    <p>Customer accounts, contact directory and records.</p>
                </a>
                <a href="../stock/index.php" class="module-card-item">
                    <h4>Stock Levels</h4>
                    <p>Inventory level monitoring across distribution channels.</p>
                </a>
                <a href="../purchases/index.php" class="module-card-item">
                    <h4>Purchases</h4>
                    <p>Supplier purchase orders and incoming inventory.</p>
                </a>
                <a href="../stock_transfers/index.php" class="module-card-item">
                    <h4>Stock Transfers</h4>
                    <p>Stockiest &rarr; Distributor &rarr; Retailer transfers.</p>
                </a>
                <a href="../sales/index.php" class="module-card-item">
                    <h4>Sales &amp; POS</h4>
                    <p>Sales transactions, invoices and customer billing.</p>
                </a>
                <a href="../reports/index.php" class="module-card-item">
                    <h4>Reports</h4>
                    <p>Financial, sales, purchase and inventory reporting.</p>
                </a>
            </div>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
