<?php
/**
 * Reports - Payment Transactions Ledger
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Payments Report';
$active_menu = 'reports';

$type_filter = trim($_GET['transaction_type'] ?? '');
$method_filter = trim($_GET['payment_method'] ?? '');
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

if (!empty($type_filter)) {
    $where_clauses[] = "p.transaction_type = ?";
    $params[] = $type_filter;
    $types .= 's';
}

if (!empty($method_filter)) {
    $where_clauses[] = "p.payment_method = ?";
    $params[] = $method_filter;
    $types .= 's';
}

if (!empty($from_date)) {
    $where_clauses[] = "p.payment_date >= ?";
    $params[] = $from_date;
    $types .= 's';
}

if (!empty($to_date)) {
    $where_clauses[] = "p.payment_date <= ?";
    $params[] = $to_date;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT p.*, u.full_name as user_name, r.role_name 
          FROM payments p 
          LEFT JOIN users u ON p.user_id = u.id 
          LEFT JOIN roles r ON u.role_id = r.id 
          {$where_sql} 
          ORDER BY p.payment_date DESC, p.id DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$total_amount = 0.00;
$rows = [];
while ($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;
    $total_amount += (float)$r['amount'];
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Payment Ledger Report</h1>
        <div class="page-breadcrumb">Smart Business / Reports / Payments</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print()" class="btn btn-secondary btn-sm">&#128438; Print Report</button>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Reports</a>
    </div>
</div>

<div class="page-content">
    <div class="stat-card-row">
        <div class="stat-card stat-green">
            <div class="stat-card-title">Total Payments Recorded</div>
            <div class="stat-card-value"><?php echo count($rows); ?></div>
            <div class="stat-card-sub">Ledger transactions</div>
        </div>
        <div class="stat-card stat-purple">
            <div class="stat-card-title">Total Transaction Value</div>
            <div class="stat-card-value">₹<?php echo number_format($total_amount, 2); ?></div>
            <div class="stat-card-sub">Sum of processed settlements</div>
        </div>
    </div>

    <div class="filter-bar no-print">
        <form method="GET" action="payments.php" class="filter-form">
            <select name="transaction_type">
                <option value="">-- All Transaction Types --</option>
                <option value="Sale" <?php echo ($type_filter === 'Sale') ? 'selected' : ''; ?>>Sale (Inward Collection)</option>
                <option value="Purchase" <?php echo ($type_filter === 'Purchase') ? 'selected' : ''; ?>>Purchase (Outward Payment)</option>
            </select>

            <select name="payment_method">
                <option value="">-- All Payment Modes --</option>
                <option value="Cash" <?php echo ($method_filter === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                <option value="Card" <?php echo ($method_filter === 'Card') ? 'selected' : ''; ?>>Card</option>
                <option value="UPI" <?php echo ($method_filter === 'UPI') ? 'selected' : ''; ?>>UPI</option>
                <option value="Bank Transfer" <?php echo ($method_filter === 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                <option value="Credit" <?php echo ($method_filter === 'Credit') ? 'selected' : ''; ?>>Credit</option>
            </select>

            <input type="date" name="from_date" value="<?php echo e($from_date); ?>" title="From Date">
            <input type="date" name="to_date" value="<?php echo e($to_date); ?>" title="To Date">

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($type_filter) || !empty($method_filter) || !empty($from_date) || !empty($to_date)): ?>
                <a href="payments.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Showing <strong><?php echo count($rows); ?></strong> record(s)
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Payment Ledger Transactions</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($rows)): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    No payment records match the specified criteria.
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 120px;">Payment ID</th>
                            <th style="width: 100px;">Date</th>
                            <th style="width: 110px;">Type</th>
                            <th style="width: 120px;">Reference #</th>
                            <?php if (is_admin()): ?>
                                <th>Processed By</th>
                            <?php endif; ?>
                            <th style="width: 110px;">Method</th>
                            <th style="text-align: right; width: 130px;">Amount (₹)</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><strong><?php echo e($r['payment_id']); ?></strong></td>
                                <td><?php echo date('d M Y', strtotime($r['payment_date'])); ?></td>
                                <td>
                                    <?php if ($r['transaction_type'] === 'Sale'): ?>
                                        <span class="badge badge-success">Sale Inflow</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Purchase Outflow</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo e($r['reference_id']); ?></strong></td>
                                <?php if (is_admin()): ?>
                                    <td><?php echo e($r['user_name'] ?? 'System'); ?></td>
                                <?php endif; ?>
                                <td><span class="badge badge-info"><?php echo e($r['payment_method']); ?></span></td>
                                <td style="text-align: right; font-weight: bold; <?php echo ($r['transaction_type'] === 'Sale') ? 'color: #2e7d32;' : 'color: #c9302c;'; ?>">
                                    ₹<?php echo number_format($r['amount'], 2); ?>
                                </td>
                                <td><?php echo e($r['notes'] ?? '—'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background: #fafbfc; font-size: 14px; font-weight: bold;">
                            <td colspan="<?php echo is_admin() ? 6 : 5; ?>" style="text-align: right;">Total Amount:</td>
                            <td style="text-align: right; color: #1a365d; font-size: 15px;">₹<?php echo number_format($total_amount, 2); ?></td>
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
