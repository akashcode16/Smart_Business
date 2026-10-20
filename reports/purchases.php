<?php
/**
 * Reports - Purchase Summary Report
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();
$page_title = 'Purchase Report';
$active_menu = 'reports';

$supplier_id = trim($_GET['supplier_id'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

if (!is_admin()) {
    $where_clauses[] = "p.user_id = ?";
    $params[] = (int)$current_user['id'];
    $types .= 'i';
}

if (!empty($supplier_id)) {
    $where_clauses[] = "p.supplier_id = ?";
    $params[] = (int)$supplier_id;
    $types .= 'i';
}

if (!empty($from_date)) {
    $where_clauses[] = "p.purchase_date >= ?";
    $params[] = $from_date;
    $types .= 's';
}

if (!empty($to_date)) {
    $where_clauses[] = "p.purchase_date <= ?";
    $params[] = $to_date;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT p.*, s.supplier_name, s.company_name, u.full_name as purchaser_name,
          COALESCE(SUM(pi.quantity), 0) as total_units,
          COUNT(pi.id) as item_count 
          FROM purchases p 
          JOIN suppliers s ON p.supplier_id = s.id 
          JOIN users u ON p.user_id = u.id 
          LEFT JOIN purchase_items pi ON p.id = pi.purchase_id 
          {$where_sql} 
          GROUP BY p.id 
          ORDER BY p.purchase_date DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch suppliers
$sups_res = mysqli_query($conn, "SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC");
$suppliers_list = [];
while ($sup = mysqli_fetch_assoc($sups_res)) {
    $suppliers_list[] = $sup;
}

$total_spend = 0.00;
$total_units = 0;
$rows = [];

while ($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;
    $total_spend += (float)$r['total_amount'];
    $total_units += (int)$r['total_units'];
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Purchases Summary Report</h1>
        <div class="page-breadcrumb">Smart Business / Reports / Purchases</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print()" class="btn btn-secondary btn-sm">&#128438; Print Report</button>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Reports</a>
    </div>
</div>

<div class="page-content">
    <div class="stat-card-row">
        <div class="stat-card stat-green">
            <div class="stat-card-title">Total Orders Placed</div>
            <div class="stat-card-value"><?php echo count($rows); ?></div>
            <div class="stat-card-sub">Procurement transactions</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Total Units Procured</div>
            <div class="stat-card-value"><?php echo number_format($total_units); ?></div>
            <div class="stat-card-sub">Inward physical units</div>
        </div>
        <div class="stat-card stat-purple">
            <div class="stat-card-title">Total Expenditure</div>
            <div class="stat-card-value">₹<?php echo number_format($total_spend, 2); ?></div>
            <div class="stat-card-sub">Sum of purchase totals</div>
        </div>
        <div class="stat-card stat-orange">
            <div class="stat-card-title">Average Order Value</div>
            <div class="stat-card-value">
                ₹<?php echo (count($rows) > 0) ? number_format($total_spend / count($rows), 2) : '0.00'; ?>
            </div>
            <div class="stat-card-sub">Per procurement order</div>
        </div>
    </div>

    <div class="filter-bar no-print">
        <form method="GET" action="purchases.php" class="filter-form">
            <select name="supplier_id">
                <option value="">-- All Suppliers --</option>
                <?php foreach ($suppliers_list as $s): ?>
                    <option value="<?php echo e($s['id']); ?>" <?php echo ((string)$supplier_id === (string)$s['id']) ? 'selected' : ''; ?>>
                        <?php echo e($s['supplier_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="from_date" value="<?php echo e($from_date); ?>" title="From Date">
            <input type="date" name="to_date" value="<?php echo e($to_date); ?>" title="To Date">

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($supplier_id) || !empty($from_date) || !empty($to_date)): ?>
                <a href="purchases.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Showing <strong><?php echo count($rows); ?></strong> order(s)
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Purchase Statement</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($rows)): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    No purchase records match the selected date range.
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 110px;">PO #</th>
                            <th style="width: 110px;">Date</th>
                            <th>Supplier</th>
                            <?php if (is_admin()): ?>
                                <th>Purchaser</th>
                            <?php endif; ?>
                            <th style="text-align: right; width: 110px;">Items</th>
                            <th style="text-align: right; width: 110px;">Units</th>
                            <th style="text-align: right; width: 130px;">Total (₹)</th>
                            <th style="width: 100px; text-align: center;">Payment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td>
                                    <a href="../purchases/view.php?id=<?php echo e($r['id']); ?>" style="color: #2b5797; font-weight: bold; text-decoration: none;">
                                        <?php echo e($r['purchase_id']); ?>
                                    </a>
                                </td>
                                <td><?php echo date('d M Y', strtotime($r['purchase_date'])); ?></td>
                                <td><?php echo e($r['supplier_name']); ?></td>
                                <?php if (is_admin()): ?>
                                    <td><?php echo e($r['purchaser_name']); ?></td>
                                <?php endif; ?>
                                <td style="text-align: right;"><?php echo (int)$r['item_count']; ?></td>
                                <td style="text-align: right; font-weight: bold;"><?php echo number_format($r['total_units']); ?></td>
                                <td style="text-align: right; font-weight: bold; color: #1a365d;">₹<?php echo number_format($r['total_amount'], 2); ?></td>
                                <td style="text-align: center;">
                                    <?php if ($r['payment_status'] === 'Paid'): ?>
                                        <span class="badge badge-success">Paid</span>
                                    <?php elseif ($r['payment_status'] === 'Partial'): ?>
                                        <span class="badge badge-warning">Partial</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #fafbfc; font-size: 14px; font-weight: bold;">
                            <td colspan="<?php echo is_admin() ? 5 : 4; ?>" style="text-align: right;">Total Purchases:</td>
                            <td style="text-align: right;"><?php echo number_format($total_units); ?></td>
                            <td style="text-align: right; color: #1a365d; font-size: 16px;">₹<?php echo number_format($total_spend, 2); ?></td>
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
