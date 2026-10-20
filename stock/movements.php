<?php
/**
 * Inventory Management - Stock Movement Audit Log
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Stock Movement Log';
$active_menu = 'stock';

$product_filter = trim($_GET['product_id'] ?? '');
$type_filter = trim($_GET['type'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

if (!is_admin()) {
    $where_clauses[] = "m.user_id = ?";
    $params[] = (int)$current_user['id'];
    $types .= 'i';
}

if (!empty($product_filter)) {
    $where_clauses[] = "m.product_id = ?";
    $params[] = (int)$product_filter;
    $types .= 'i';
}

if (!empty($type_filter)) {
    $where_clauses[] = "m.movement_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

if (!empty($from_date)) {
    $where_clauses[] = "DATE(m.created_at) >= ?";
    $params[] = $from_date;
    $types .= 's';
}

if (!empty($to_date)) {
    $where_clauses[] = "DATE(m.created_at) <= ?";
    $params[] = $to_date;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT m.*, p.product_id as sku, p.product_name, p.unit, u.full_name as user_name, r.role_name 
          FROM stock_movements m 
          JOIN products p ON m.product_id = p.id 
          JOIN users u ON m.user_id = u.id 
          JOIN roles r ON u.role_id = r.id 
          {$where_sql} 
          ORDER BY m.id DESC 
          LIMIT 200";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch products for filter
$p_res = mysqli_query($conn, "SELECT id, product_name, product_id FROM products ORDER BY product_name ASC");
$prod_list = [];
while ($p = mysqli_fetch_assoc($p_res)) {
    $prod_list[] = $p;
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Stock Movement Audit Log</h1>
        <div class="page-breadcrumb">Smart Business / Stock / Movements</div>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Stock</a>
    </div>
</div>

<div class="page-content">
    <div class="filter-bar">
        <form method="GET" action="movements.php" class="filter-form">
            <select name="product_id">
                <option value="">-- All Products --</option>
                <?php foreach ($prod_list as $prod): ?>
                    <option value="<?php echo e($prod['id']); ?>" <?php echo ((string)$product_filter === (string)$prod['id']) ? 'selected' : ''; ?>>
                        <?php echo e($prod['product_name']); ?> (<?php echo e($prod['product_id']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="type">
                <option value="">-- Movement Type --</option>
                <option value="Opening" <?php echo ($type_filter === 'Opening') ? 'selected' : ''; ?>>Opening Stock</option>
                <option value="Purchase" <?php echo ($type_filter === 'Purchase') ? 'selected' : ''; ?>>Purchase</option>
                <option value="Transfer In" <?php echo ($type_filter === 'Transfer In') ? 'selected' : ''; ?>>Transfer In</option>
                <option value="Transfer Out" <?php echo ($type_filter === 'Transfer Out') ? 'selected' : ''; ?>>Transfer Out</option>
                <option value="Sale" <?php echo ($type_filter === 'Sale') ? 'selected' : ''; ?>>Sale</option>
                <option value="Adjustment" <?php echo ($type_filter === 'Adjustment') ? 'selected' : ''; ?>>Adjustment</option>
            </select>

            <input type="date" name="from_date" value="<?php echo e($from_date); ?>" title="From Date">
            <input type="date" name="to_date" value="<?php echo e($to_date); ?>" title="To Date">

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($product_filter) || !empty($type_filter) || !empty($from_date) || !empty($to_date)): ?>
                <a href="movements.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Showing <strong><?php echo mysqli_num_rows($result); ?></strong> event(s)
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Inventory Movement History</span>
            <span class="badge badge-info">Chronological Audit Records</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (mysqli_num_rows($result) === 0): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    No stock movement events recorded yet.
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 130px;">Date &amp; Time</th>
                            <?php if (is_admin()): ?>
                                <th>Account Holder</th>
                            <?php endif; ?>
                            <th>Product</th>
                            <th style="width: 120px;">Movement Type</th>
                            <th style="text-align: right; width: 90px;">Quantity</th>
                            <th style="text-align: right; width: 90px;">Before</th>
                            <th style="text-align: right; width: 90px;">After</th>
                            <th>Reference / Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($m = mysqli_fetch_assoc($result)): 
                            $is_positive = ($m['quantity'] > 0);
                        ?>
                            <tr>
                                <td><?php echo date('d M Y, h:i A', strtotime($m['created_at'])); ?></td>
                                <?php if (is_admin()): ?>
                                    <td>
                                        <strong><?php echo e($m['user_name']); ?></strong>
                                        <span class="badge-role badge-<?php echo strtolower($m['role_name']); ?>" style="font-size: 10px;">
                                            <?php echo e($m['role_name']); ?>
                                        </span>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <strong><?php echo e($m['product_name']); ?></strong>
                                    <div style="font-size: 11px; color: #777;">Code: <?php echo e($m['sku']); ?></div>
                                </td>
                                <td>
                                    <?php
                                    $type_class = 'badge-info';
                                    if ($m['movement_type'] === 'Purchase' || $m['movement_type'] === 'Transfer In' || $m['movement_type'] === 'Opening') {
                                        $type_class = 'badge-success';
                                    } elseif ($m['movement_type'] === 'Sale' || $m['movement_type'] === 'Transfer Out') {
                                        $type_class = 'badge-danger';
                                    } elseif ($m['movement_type'] === 'Adjustment') {
                                        $type_class = 'badge-warning';
                                    }
                                    ?>
                                    <span class="badge <?php echo $type_class; ?>"><?php echo e($m['movement_type']); ?></span>
                                </td>
                                <td style="text-align: right; font-weight: bold; <?php echo $is_positive ? 'color: #2e7d32;' : 'color: #c9302c;'; ?>">
                                    <?php echo $is_positive ? '+' : ''; ?><?php echo number_format($m['quantity']); ?> <?php echo e($m['unit']); ?>
                                </td>
                                <td style="text-align: right; color: #666;"><?php echo number_format($m['balance_before']); ?></td>
                                <td style="text-align: right; font-weight: bold;"><?php echo number_format($m['balance_after']); ?></td>
                                <td>
                                    <?php if (!empty($m['reference_id'])): ?>
                                        <strong>[<?php echo e($m['reference_id']); ?>]</strong>
                                    <?php endif; ?>
                                    <?php echo e($m['notes']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
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
