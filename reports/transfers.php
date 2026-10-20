<?php
/**
 * Reports - Stock Transfers Report
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest', 'Distributor']);

$current_user = get_logged_in_user();
$page_title = 'Transfers Report';
$active_menu = 'reports';

$product_id = trim($_GET['product_id'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

if (!is_admin()) {
    $uid = (int)$current_user['id'];
    $where_clauses[] = "(st.from_user_id = ? OR st.to_user_id = ?)";
    $params[] = $uid;
    $params[] = $uid;
    $types .= 'ii';
}

if (!empty($product_id)) {
    $where_clauses[] = "st.product_id = ?";
    $params[] = (int)$product_id;
    $types .= 'i';
}

if (!empty($from_date)) {
    $where_clauses[] = "st.transfer_date >= ?";
    $params[] = $from_date;
    $types .= 's';
}

if (!empty($to_date)) {
    $where_clauses[] = "st.transfer_date <= ?";
    $params[] = $to_date;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT st.*, 
          p.product_id as sku, p.product_name, p.unit,
          u_from.full_name as from_name, r_from.role_name as from_role,
          u_to.full_name as to_name, r_to.role_name as to_role 
          FROM stock_transfers st 
          JOIN products p ON st.product_id = p.id 
          JOIN users u_from ON st.from_user_id = u_from.id 
          JOIN roles r_from ON u_from.role_id = r_from.id 
          JOIN users u_to ON st.to_user_id = u_to.id 
          JOIN roles r_to ON u_to.role_id = r_to.id 
          {$where_sql} 
          ORDER BY st.transfer_date DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch products
$p_res = mysqli_query($conn, "SELECT id, product_name, product_id FROM products ORDER BY product_name ASC");
$prod_list = [];
while ($p = mysqli_fetch_assoc($p_res)) {
    $prod_list[] = $p;
}

$tot_trans_units = 0;
$rows = [];
while ($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;
    $tot_trans_units += (int)$r['quantity'];
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Stock Transfer Report</h1>
        <div class="page-breadcrumb">Smart Business / Reports / Transfers</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print()" class="btn btn-secondary btn-sm">&#128438; Print Report</button>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Reports</a>
    </div>
</div>

<div class="page-content">
    <div class="stat-card-row">
        <div class="stat-card stat-orange">
            <div class="stat-card-title">Transfer Transactions</div>
            <div class="stat-card-value"><?php echo count($rows); ?></div>
            <div class="stat-card-sub">Stock relocation events</div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-card-title">Total Units Transferred</div>
            <div class="stat-card-value"><?php echo number_format($tot_trans_units); ?></div>
            <div class="stat-card-sub">Combined physical volume</div>
        </div>
    </div>

    <div class="filter-bar no-print">
        <form method="GET" action="transfers.php" class="filter-form">
            <select name="product_id">
                <option value="">-- All Products --</option>
                <?php foreach ($prod_list as $prod): ?>
                    <option value="<?php echo e($prod['id']); ?>" <?php echo ((string)$product_id === (string)$prod['id']) ? 'selected' : ''; ?>>
                        <?php echo e($prod['product_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="from_date" value="<?php echo e($from_date); ?>" title="From Date">
            <input type="date" name="to_date" value="<?php echo e($to_date); ?>" title="To Date">

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($product_id) || !empty($from_date) || !empty($to_date)): ?>
                <a href="transfers.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Dispatches: <strong><?php echo count($rows); ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Transfer Records</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($rows)): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    No transfer records match the selected date range.
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 110px;">Transfer #</th>
                            <th style="width: 100px;">Date</th>
                            <th>Sender Party</th>
                            <th>Consigned To</th>
                            <th>Product Description</th>
                            <th style="text-align: right; width: 120px;">Transferred Qty</th>
                            <th style="width: 100px; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <a href="../stock_transfers/view.php?id=<?php echo e($r['id']); ?>" style="color: #2b5797; font-weight: bold; text-decoration: none;">
                                        <?php echo e($r['transfer_id']); ?>
                                    </a>
                                </td>
                                <td><?php echo date('d M Y', strtotime($r['transfer_date'])); ?></td>
                                <td>
                                    <?php echo e($r['from_name']); ?> 
                                    <span class="badge-role badge-<?php echo strtolower($r['from_role']); ?>" style="font-size: 10px;">
                                        <?php echo e($r['from_role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo e($r['to_name']); ?> 
                                    <span class="badge-role badge-<?php echo strtolower($r['to_role']); ?>" style="font-size: 10px;">
                                        <?php echo e($r['to_role']); ?>
                                    </span>
                                </td>
                                <td><strong><?php echo e($r['product_name']); ?></strong> (<?php echo e($r['sku']); ?>)</td>
                                <td style="text-align: right; font-weight: bold; color: #1a365d;">
                                    <?php echo number_format($r['quantity']); ?> <?php echo e($r['unit']); ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-success">&check; <?php echo e($r['status']); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #fafbfc; font-size: 14px; font-weight: bold;">
                            <td colspan="5" style="text-align: right;">Total Relocated Volume:</td>
                            <td style="text-align: right; font-size: 15px; color: #1a365d;"><?php echo number_format($tot_trans_units); ?> units</td>
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
