<?php
/**
 * Reports - Sales & Revenue Report
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Sales & Revenue Report';
$active_menu = 'reports';

$status_filter = trim($_GET['payment_status'] ?? '');
$method_filter = trim($_GET['payment_method'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

if (!is_admin()) {
    $where_clauses[] = "s.user_id = ?";
    $params[] = (int)$current_user['id'];
    $types .= 'i';
}

if (!empty($status_filter)) {
    $where_clauses[] = "s.payment_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($method_filter)) {
    $where_clauses[] = "s.payment_method = ?";
    $params[] = $method_filter;
    $types .= 's';
}

if (!empty($from_date)) {
    $where_clauses[] = "s.sale_date >= ?";
    $params[] = $from_date;
    $types .= 's';
}

if (!empty($to_date)) {
    $where_clauses[] = "s.sale_date <= ?";
    $params[] = $to_date;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT s.*, 
          COALESCE(c.customer_name, 'Walk-in Retail') as customer_name,
          u.full_name as seller_name,
          COALESCE(SUM(p.amount), 0) as amount_collected,
          COALESCE(SUM(si.quantity), 0) as total_items 
          FROM sales s 
          LEFT JOIN customers c ON s.customer_id = c.id 
          JOIN users u ON s.user_id = u.id 
          LEFT JOIN sale_items si ON s.id = si.sale_id 
          LEFT JOIN payments p ON p.transaction_type = 'Sale' AND p.reference_id = s.sale_id 
          {$where_sql} 
          GROUP BY s.id 
          ORDER BY s.sale_date DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$total_billed = 0.00;
$total_collected = 0.00;
$total_units_sold = 0;
$rows = [];

while ($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;
    $total_billed += (float)$r['total_amount'];
    $total_collected += (float)$r['amount_collected'];
    $total_units_sold += (int)$r['total_items'];
}

$outstanding = max(0, $total_billed - $total_collected);

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Sales &amp; Revenue Analytics</h1>
        <div class="page-breadcrumb">Smart Business / Reports / Sales</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print()" class="btn btn-secondary btn-sm">&#128438; Print Report</button>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Reports</a>
    </div>
</div>

<div class="page-content">
    <div class="stat-card-row">
        <div class="stat-card stat-purple">
            <div class="stat-card-title">Total Sales Invoiced</div>
            <div class="stat-card-value">₹<?php echo number_format($total_billed, 2); ?></div>
            <div class="stat-card-sub"><?php echo count($rows); ?> transactions generated</div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-card-title">Payments Collected</div>
            <div class="stat-card-value">₹<?php echo number_format($total_collected, 2); ?></div>
            <div class="stat-card-sub">Liquid collections received</div>
        </div>
        <div class="stat-card <?php echo ($outstanding > 0) ? 'stat-red' : ''; ?>">
            <div class="stat-card-title">Receivables Outstanding</div>
            <div class="stat-card-value" style="<?php echo ($outstanding > 0) ? 'color: #c9302c;' : ''; ?>">
                ₹<?php echo number_format($outstanding, 2); ?>
            </div>
            <div class="stat-card-sub">Customer credit pending</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Total Units Sold</div>
            <div class="stat-card-value"><?php echo number_format($total_units_sold); ?></div>
            <div class="stat-card-sub">Product quantity outflow</div>
        </div>
    </div>

    <div class="filter-bar no-print">
        <form method="GET" action="sales.php" class="filter-form">
            <select name="payment_status">
                <option value="">-- Payment Status --</option>
                <option value="Paid" <?php echo ($status_filter === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                <option value="Partial" <?php echo ($status_filter === 'Partial') ? 'selected' : ''; ?>>Partial</option>
                <option value="Unpaid" <?php echo ($status_filter === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
            </select>

            <select name="payment_method">
                <option value="">-- Payment Method --</option>
                <option value="Cash" <?php echo ($method_filter === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                <option value="Card" <?php echo ($method_filter === 'Card') ? 'selected' : ''; ?>>Card</option>
                <option value="UPI" <?php echo ($method_filter === 'UPI') ? 'selected' : ''; ?>>UPI</option>
                <option value="Bank Transfer" <?php echo ($method_filter === 'Bank Transfer') ? 'selected' : ''; ?>>Bank Transfer</option>
                <option value="Credit" <?php echo ($method_filter === 'Credit') ? 'selected' : ''; ?>>Credit</option>
            </select>

            <input type="date" name="from_date" value="<?php echo e($from_date); ?>" title="From Date">
            <input type="date" name="to_date" value="<?php echo e($to_date); ?>" title="To Date">

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($status_filter) || !empty($method_filter) || !empty($from_date) || !empty($to_date)): ?>
                <a href="sales.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Showing <strong><?php echo count($rows); ?></strong> invoice(s)
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Sales Revenue Ledger</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($rows)): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    No sales records found matching the criteria.
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 110px;">Invoice #</th>
                            <th style="width: 100px;">Date</th>
                            <th>Customer</th>
                            <?php if (is_admin()): ?>
                                <th>Billed By</th>
                            <?php endif; ?>
                            <th style="width: 100px;">Mode</th>
                            <th style="text-align: right; width: 110px;">Billed Total</th>
                            <th style="text-align: right; width: 110px;">Collected</th>
                            <th style="text-align: right; width: 110px;">Balance</th>
                            <th style="width: 90px; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): 
                            $bal = max(0, $r['total_amount'] - $r['amount_collected']);
                        ?>
                            <tr>
                                <td>
                                    <a href="../sales/view.php?id=<?php echo e($r['id']); ?>" style="color: #2b5797; font-weight: bold; text-decoration: none;">
                                        <?php echo e($r['sale_id']); ?>
                                    </a>
                                </td>
                                <td><?php echo date('d M Y', strtotime($r['sale_date'])); ?></td>
                                <td><?php echo e($r['customer_name']); ?></td>
                                <?php if (is_admin()): ?>
                                    <td><?php echo e($r['seller_name']); ?></td>
                                <?php endif; ?>
                                <td><span class="badge badge-info"><?php echo e($r['payment_method']); ?></span></td>
                                <td style="text-align: right; font-weight: bold;">₹<?php echo number_format($r['total_amount'], 2); ?></td>
                                <td style="text-align: right; color: #2e7d32; font-weight: bold;">₹<?php echo number_format($r['amount_collected'], 2); ?></td>
                                <td style="text-align: right; <?php echo ($bal > 0) ? 'color: #c9302c; font-weight: bold;' : 'color: #777;'; ?>">
                                    ₹<?php echo number_format($bal, 2); ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($bal <= 0 || $r['payment_status'] === 'Paid'): ?>
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
                            <td colspan="<?php echo is_admin() ? 5 : 4; ?>" style="text-align: right;">Ledger Sum Total:</td>
                            <td style="text-align: right; color: #1a365d; font-size: 15px;">₹<?php echo number_format($total_billed, 2); ?></td>
                            <td style="text-align: right; color: #2e7d32; font-size: 15px;">₹<?php echo number_format($total_collected, 2); ?></td>
                            <td style="text-align: right; color: #c9302c; font-size: 15px;">₹<?php echo number_format($outstanding, 2); ?></td>
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
