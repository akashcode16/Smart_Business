<?php
/**
 * Reports - Stock & Inventory Report
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Stock Report';
$active_menu = 'reports';

$category_id = trim($_GET['category_id'] ?? '');
$low_stock = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';

$where_clauses = [];
$params = [];
$types = '';

if (!is_admin()) {
    $where_clauses[] = "s.user_id = ?";
    $params[] = (int)$current_user['id'];
    $types .= 'i';
}

if (!empty($category_id)) {
    $where_clauses[] = "p.category_id = ?";
    $params[] = (int)$category_id;
    $types .= 'i';
}

if ($low_stock) {
    $where_clauses[] = "s.quantity <= p.minimum_stock_level";
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT s.*, p.product_id as sku, p.product_name, p.unit, p.purchase_price, p.selling_price, p.minimum_stock_level,
          c.category_name, u.full_name as owner_name, r.role_name as owner_role 
          FROM stock s 
          JOIN products p ON s.product_id = p.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          JOIN users u ON s.user_id = u.id 
          JOIN roles r ON u.role_id = r.id 
          {$where_sql} 
          ORDER BY p.product_name ASC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch categories
$cats_res = mysqli_query($conn, "SELECT id, category_name FROM categories ORDER BY category_name ASC");
$categories_list = [];
while ($c = mysqli_fetch_assoc($cats_res)) {
    $categories_list[] = $c;
}

$tot_qty = 0;
$tot_cost_val = 0.00;
$tot_retail_val = 0.00;
$rows = [];

while ($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;
    $tot_qty += (int)$r['quantity'];
    $tot_cost_val += ($r['quantity'] * $r['purchase_price']);
    $tot_retail_val += ($r['quantity'] * $r['selling_price']);
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Stock &amp; Inventory Report</h1>
        <div class="page-breadcrumb">Smart Business / Reports / Stock</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print()" class="btn btn-secondary btn-sm">&#128438; Print Report</button>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Reports</a>
    </div>
</div>

<div class="page-content">
    <div class="stat-card-row">
        <div class="stat-card stat-green">
            <div class="stat-card-title">Total Units on Hand</div>
            <div class="stat-card-value"><?php echo number_format($tot_qty); ?></div>
            <div class="stat-card-sub">Physical product units</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Cost Valuation</div>
            <div class="stat-card-value">₹<?php echo number_format($tot_cost_val, 2); ?></div>
            <div class="stat-card-sub">Total procurement cost</div>
        </div>
        <div class="stat-card stat-purple">
            <div class="stat-card-title">Retail Valuation</div>
            <div class="stat-card-value">₹<?php echo number_format($tot_retail_val, 2); ?></div>
            <div class="stat-card-sub">Expected sales revenue</div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-card-title">Gross Profit Potential</div>
            <div class="stat-card-value">₹<?php echo number_format(max(0, $tot_retail_val - $tot_cost_val), 2); ?></div>
            <div class="stat-card-sub">Unrealized profit margin</div>
        </div>
    </div>

    <div class="filter-bar no-print">
        <form method="GET" action="stock.php" class="filter-form">
            <select name="category_id">
                <option value="">-- All Categories --</option>
                <?php foreach ($categories_list as $cat): ?>
                    <option value="<?php echo e($cat['id']); ?>" <?php echo ((string)$category_id === (string)$cat['id']) ? 'selected' : ''; ?>>
                        <?php echo e($cat['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div class="checkbox-group" style="margin-top: 0;">
                <input type="checkbox" id="low_stock" name="low_stock" value="1" <?php echo $low_stock ? 'checked' : ''; ?> onchange="this.form.submit()">
                <label for="low_stock" style="font-size: 13px; font-weight: normal; cursor: pointer; color: #c9302c;">Show Low Stock Items Only</label>
            </div>

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($category_id) || $low_stock): ?>
                <a href="stock.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Items: <strong><?php echo count($rows); ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Inventory Valuation Table</span>
            <span style="font-size: 12px; color: #666;">Report generated: <?php echo date('d M Y, h:i A'); ?></span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($rows)): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    No inventory records match the selected filter.
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
                            <th style="text-align: right; width: 100px;">Qty on Hand</th>
                            <th style="text-align: right; width: 110px;">Cost Price</th>
                            <th style="text-align: right; width: 110px;">Selling Price</th>
                            <th style="text-align: right; width: 120px;">Cost Value</th>
                            <th style="text-align: right; width: 120px;">Retail Value</th>
                            <th style="width: 90px; text-align: center;">Alert</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): 
                            $c_val = $r['quantity'] * $r['purchase_price'];
                            $r_val = $r['quantity'] * $r['selling_price'];
                            $is_warn = ($r['quantity'] <= $r['minimum_stock_level']);
                        ?>
                            <tr>
                                <td><strong><?php echo e($r['sku']); ?></strong></td>
                                <td><?php echo e($r['product_name']); ?></td>
                                <td><?php echo e($r['category_name'] ?? '—'); ?></td>
                                <?php if (is_admin()): ?>
                                    <td><?php echo e($r['owner_name']); ?> (<?php echo e($r['owner_role']); ?>)</td>
                                <?php endif; ?>
                                <td style="text-align: right; font-weight: bold; <?php echo $is_warn ? 'color: #c9302c;' : ''; ?>">
                                    <?php echo number_format($r['quantity']); ?> <?php echo e($r['unit']); ?>
                                </td>
                                <td style="text-align: right;">₹<?php echo number_format($r['purchase_price'], 2); ?></td>
                                <td style="text-align: right;">₹<?php echo number_format($r['selling_price'], 2); ?></td>
                                <td style="text-align: right;">₹<?php echo number_format($c_val, 2); ?></td>
                                <td style="text-align: right; font-weight: bold; color: #2e7d32;">₹<?php echo number_format($r_val, 2); ?></td>
                                <td style="text-align: center;">
                                    <?php if ($is_warn): ?>
                                        <span class="badge badge-warning">Low</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #fafbfc; font-size: 14px; font-weight: bold;">
                            <td colspan="<?php echo is_admin() ? 4 : 3; ?>" style="text-align: right;">Totals:</td>
                            <td style="text-align: right;"><?php echo number_format($tot_qty); ?></td>
                            <td colspan="2"></td>
                            <td style="text-align: right;">₹<?php echo number_format($tot_cost_val, 2); ?></td>
                            <td style="text-align: right; color: #2e7d32; font-size: 15px;">₹<?php echo number_format($tot_retail_val, 2); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if ($stmt) mysqli_stmt_close($stmt);
include '../includes/footer.php';
?>
