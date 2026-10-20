<?php
/**
 * Inventory & Stock Management - Current Stock Level
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Allowed for all 4 roles
require_role(['Admin', 'Stockiest', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Inventory & Stock';
$active_menu = 'stock';

$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');
$user_filter = trim($_GET['user_id'] ?? '');
$low_stock_only = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';

$where_clauses = [];
$params = [];
$types = '';

// Role restriction: Non-admin users only see their own stock
if (!is_admin()) {
    $where_clauses[] = "s.user_id = ?";
    $params[] = (int)$current_user['id'];
    $types .= 'i';
} else {
    // Admin can optionally filter by a specific business user
    if (!empty($user_filter)) {
        $where_clauses[] = "s.user_id = ?";
        $params[] = (int)$user_filter;
        $types .= 'i';
    }
}

if (!empty($search)) {
    $where_clauses[] = "(p.product_id LIKE ? OR p.product_name LIKE ? OR p.brand LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if (!empty($category_filter)) {
    $where_clauses[] = "p.category_id = ?";
    $params[] = (int)$category_filter;
    $types .= 'i';
}

if ($low_stock_only) {
    $where_clauses[] = "s.quantity <= p.minimum_stock_level";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT s.*, p.product_id as sku, p.product_name, p.brand, p.unit, p.purchase_price, p.selling_price, p.minimum_stock_level, 
          c.category_name, u.full_name as owner_name, u.user_id as owner_code, r.role_name as owner_role 
          FROM stock s 
          JOIN products p ON s.product_id = p.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          JOIN users u ON s.user_id = u.id 
          JOIN roles r ON u.role_id = r.id 
          {$where_sql} 
          ORDER BY (s.quantity <= p.minimum_stock_level) DESC, p.product_name ASC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Calculate totals
$total_units = 0;
$total_valuation = 0.00;
$low_stock_count = 0;
$stock_rows = [];

while ($row = mysqli_fetch_assoc($result)) {
    $stock_rows[] = $row;
    $total_units += (int)$row['quantity'];
    $total_valuation += ($row['quantity'] * $row['purchase_price']);
    if ((int)$row['quantity'] <= (int)$row['minimum_stock_level']) {
        $low_stock_count++;
    }
}

// Fetch categories for filter
$cats_res = mysqli_query($conn, "SELECT id, category_name FROM categories ORDER BY category_name ASC");
$categories_list = [];
while ($c = mysqli_fetch_assoc($cats_res)) {
    $categories_list[] = $c;
}

// Fetch business users for Admin filter
$users_list = [];
if (is_admin()) {
    $u_res = mysqli_query($conn, "SELECT u.id, u.user_id, u.full_name, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name != 'Admin' ORDER BY r.id, u.full_name");
    while ($u = mysqli_fetch_assoc($u_res)) {
        $users_list[] = $u;
    }
}

$flash_messages = get_flash();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Inventory &amp; Stock Levels</h1>
        <div class="page-breadcrumb">Smart Business / Stock</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="adjust.php" class="btn btn-warning btn-sm">&plusmn; Stock Adjustment</a>
        <a href="movements.php" class="btn btn-secondary btn-sm">&#9776; Movement Log</a>
    </div>
</div>

<div class="page-content">
    <?php foreach ($flash_messages as $flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endforeach; ?>

    <!-- Summary Stat Cards -->
    <div class="stat-card-row">
        <div class="stat-card stat-green">
            <div class="stat-card-title">Total Products in Stock</div>
            <div class="stat-card-value"><?php echo count($stock_rows); ?></div>
            <div class="stat-card-sub">Active SKU inventory items</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Total Quantity on Hand</div>
            <div class="stat-card-value"><?php echo number_format($total_units); ?></div>
            <div class="stat-card-sub">Combined physical units</div>
        </div>
        <div class="stat-card stat-purple">
            <div class="stat-card-title">Total Inventory Valuation</div>
            <div class="stat-card-value">₹<?php echo number_format($total_valuation, 2); ?></div>
            <div class="stat-card-sub">Based on cost purchase price</div>
        </div>
        <div class="stat-card <?php echo ($low_stock_count > 0) ? 'stat-red' : ''; ?>">
            <div class="stat-card-title">Low Stock Warnings</div>
            <div class="stat-card-value" style="<?php echo ($low_stock_count > 0) ? 'color: #c9302c;' : ''; ?>">
                <?php echo $low_stock_count; ?>
            </div>
            <div class="stat-card-sub">Items at or below threshold</div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" action="index.php" class="filter-form">
            <input type="text" name="search" placeholder="Search product name, SKU..." value="<?php echo e($search); ?>" style="min-width: 200px;">
            
            <select name="category">
                <option value="">-- All Categories --</option>
                <?php foreach ($categories_list as $cat): ?>
                    <option value="<?php echo e($cat['id']); ?>" <?php echo ((string)$category_filter === (string)$cat['id']) ? 'selected' : ''; ?>>
                        <?php echo e($cat['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <?php if (is_admin()): ?>
                <select name="user_id">
                    <option value="">-- All Business Accounts --</option>
                    <?php foreach ($users_list as $usr): ?>
                        <option value="<?php echo e($usr['id']); ?>" <?php echo ((string)$user_filter === (string)$usr['id']) ? 'selected' : ''; ?>>
                            <?php echo e($usr['full_name']); ?> (<?php echo e($usr['role_name']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <div class="checkbox-group" style="margin-top: 0;">
                <input type="checkbox" id="low_stock" name="low_stock" value="1" <?php echo $low_stock_only ? 'checked' : ''; ?> onchange="this.form.submit()">
                <label for="low_stock" style="font-size: 13px; font-weight: normal; cursor: pointer; color: #c9302c;">Show Low Stock Only</label>
            </div>

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($search) || !empty($category_filter) || !empty($user_filter) || $low_stock_only): ?>
                <a href="index.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Showing <strong><?php echo count($stock_rows); ?></strong> record(s)
        </div>
    </div>

    <!-- Stock Table -->
    <div class="card">
        <div class="card-header">
            <span>Available Inventory Records</span>
            <span class="badge badge-info"><?php echo is_admin() ? 'All Entity Accounts' : e($current_user['full_name']) . ' Stock'; ?></span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($stock_rows)): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    <p style="font-size: 15px; margin-bottom: 8px;">No stock records found.</p>
                    <?php if (is_stockiest()): ?>
                        <a href="../purchases/create.php" class="btn btn-primary btn-sm">+ Make a Supplier Purchase</a>
                    <?php else: ?>
                        <p style="font-size: 13px; color: #888;">Inventory will appear once stock is transferred or received from your distributor/stockiest.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 100px;">SKU</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <?php if (is_admin()): ?>
                                <th>Account Holder</th>
                            <?php endif; ?>
                            <th style="text-align: right; width: 100px;">Cost Price</th>
                            <th style="text-align: right; width: 100px;">Selling Price</th>
                            <th style="text-align: right; width: 110px;">Available Qty</th>
                            <th style="text-align: right; width: 120px;">Total Valuation</th>
                            <th style="width: 100px; text-align: center;">Alert Status</th>
                            <th style="width: 120px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_rows as $row): 
                            $is_low = ((int)$row['quantity'] <= (int)$row['minimum_stock_level']);
                            $val = $row['quantity'] * $row['purchase_price'];
                        ?>
                            <tr style="<?php echo $is_low ? 'background-color: #fff8f8;' : ''; ?>">
                                <td><strong><?php echo e($row['sku']); ?></strong></td>
                                <td>
                                    <a href="../products/view.php?id=<?php echo e($row['product_id']); ?>" style="color: #2b5797; font-weight: bold; text-decoration: none;">
                                        <?php echo e($row['product_name']); ?>
                                    </a>
                                    <?php if (!empty($row['brand'])): ?>
                                        <div style="font-size: 11px; color: #777;"><?php echo e($row['brand']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($row['category_name'] ?? '—'); ?></td>
                                <?php if (is_admin()): ?>
                                    <td>
                                        <strong><?php echo e($row['owner_name']); ?></strong>
                                        <span class="badge-role badge-<?php echo strtolower($row['owner_role']); ?>" style="font-size: 10px;">
                                            <?php echo e($row['owner_role']); ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td style="text-align: right;">₹<?php echo number_format($row['purchase_price'], 2); ?></td>
                                <td style="text-align: right; color: #2e7d32; font-weight: bold;">₹<?php echo number_format($row['selling_price'], 2); ?></td>
                                <td style="text-align: right;">
                                    <strong style="font-size: 14px; <?php echo $is_low ? 'color: #c9302c;' : 'color: #1a365d;'; ?>">
                                        <?php echo number_format($row['quantity']); ?>
                                    </strong> 
                                    <span style="font-size: 11px; color: #666;"><?php echo e($row['unit']); ?></span>
                                </td>
                                <td style="text-align: right; font-weight: bold;">₹<?php echo number_format($val, 2); ?></td>
                                <td style="text-align: center;">
                                    <?php if ($row['quantity'] <= 0): ?>
                                        <span class="badge badge-danger">Out of Stock</span>
                                    <?php elseif ($is_low): ?>
                                        <span class="badge badge-warning" title="Min level: <?php echo e($row['minimum_stock_level']); ?>">Low Stock (&le;<?php echo e($row['minimum_stock_level']); ?>)</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">Adequate</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <a href="adjust.php?product_id=<?php echo e($row['product_id']); ?>&user_id=<?php echo e($row['user_id']); ?>" class="btn-warning" style="font-size: 11px;">Adjust</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if ($stmt) {
    mysqli_stmt_close($stmt);
}
include '../includes/footer.php';
?>
