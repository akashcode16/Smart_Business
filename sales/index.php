<?php
/**
 * Sales Management - Sales Invoices List
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Allowed for Admin, Distributor, and Retailer
require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Sales & Billing';
$active_menu = 'sales';

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['payment_status'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

// Role restriction: Non-admin only sees sales made by their own account
if (!is_admin()) {
    $where_clauses[] = "s.user_id = ?";
    $params[] = (int)$current_user['id'];
    $types .= 'i';
}

if (!empty($search)) {
    $where_clauses[] = "(s.sale_id LIKE ? OR c.customer_name LIKE ? OR c.phone LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if (!empty($status_filter)) {
    $where_clauses[] = "s.payment_status = ?";
    $params[] = $status_filter;
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
          COALESCE(c.customer_name, 'Walk-in Customer') as customer_name,
          c.phone as customer_phone,
          u.full_name as seller_name,
          COALESCE(SUM(p.amount), 0) as amount_paid,
          COUNT(DISTINCT si.id) as item_count,
          COALESCE(SUM(si.quantity), 0) as total_units 
          FROM sales s 
          LEFT JOIN customers c ON s.customer_id = c.id 
          JOIN users u ON s.user_id = u.id 
          LEFT JOIN sale_items si ON s.id = si.sale_id 
          LEFT JOIN payments p ON p.transaction_type = 'Sale' AND p.reference_id = s.sale_id 
          {$where_sql} 
          GROUP BY s.id 
          ORDER BY s.sale_date DESC, s.id DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$flash_messages = get_flash();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Sales &amp; POS Billing</h1>
        <div class="page-breadcrumb">Smart Business / Sales</div>
    </div>
    <div>
        <a href="create.php" class="btn btn-primary">+ New Sale / POS Invoice</a>
    </div>
</div>

<div class="page-content">
    <?php foreach ($flash_messages as $flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endforeach; ?>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" action="index.php" class="filter-form">
            <input type="text" name="search" placeholder="Search Invoice #, customer..." value="<?php echo e($search); ?>" style="min-width: 220px;">
            
            <select name="payment_status">
                <option value="">-- All Payment Statuses --</option>
                <option value="Paid" <?php echo ($status_filter === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                <option value="Partial" <?php echo ($status_filter === 'Partial') ? 'selected' : ''; ?>>Partial</option>
                <option value="Unpaid" <?php echo ($status_filter === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
            </select>

            <input type="date" name="from_date" value="<?php echo e($from_date); ?>" title="From Date">
            <input type="date" name="to_date" value="<?php echo e($to_date); ?>" title="To Date">

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($search) || !empty($status_filter) || !empty($from_date) || !empty($to_date)): ?>
                <a href="index.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Invoices: <strong><?php echo mysqli_num_rows($result); ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Sales Invoices</span>
            <span class="badge badge-info">POS &amp; Customer Billing</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (mysqli_num_rows($result) === 0): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    <p style="font-size: 15px; margin-bottom: 8px;">No sales invoices recorded yet.</p>
                    <a href="create.php" class="btn btn-primary btn-sm">+ Create POS Invoice</a>
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
                            <th style="text-align: right; width: 110px;">Total (₹)</th>
                            <th style="text-align: right; width: 110px;">Paid (₹)</th>
                            <th style="text-align: right; width: 110px;">Balance (₹)</th>
                            <th style="width: 100px; text-align: center;">Status</th>
                            <th style="width: 180px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): 
                            $balance = max(0, $row['total_amount'] - $row['amount_paid']);
                        ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?php echo e($row['id']); ?>" style="color: #2b5797; font-weight: bold; text-decoration: none;">
                                        <?php echo e($row['sale_id']); ?>
                                    </a>
                                </td>
                                <td><?php echo date('d M Y', strtotime($row['sale_date'])); ?></td>
                                <td>
                                    <strong><?php echo e($row['customer_name']); ?></strong>
                                    <?php if (!empty($row['customer_phone'])): ?>
                                        <div style="font-size: 11px; color: #777;"><?php echo e($row['customer_phone']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php if (is_admin()): ?>
                                    <td><?php echo e($row['seller_name']); ?></td>
                                <?php endif; ?>
                                <td style="text-align: right; font-weight: bold; color: #1a365d;">
                                    ₹<?php echo number_format($row['total_amount'], 2); ?>
                                </td>
                                <td style="text-align: right; color: #2e7d32; font-weight: bold;">
                                    ₹<?php echo number_format($row['amount_paid'], 2); ?>
                                </td>
                                <td style="text-align: right; font-weight: bold; <?php echo ($balance > 0) ? 'color: #c9302c;' : 'color: #777;'; ?>">
                                    ₹<?php echo number_format($balance, 2); ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($row['payment_status'] === 'Paid' || $balance <= 0): ?>
                                        <span class="badge badge-success">Paid</span>
                                    <?php elseif ($row['payment_status'] === 'Partial'): ?>
                                        <span class="badge badge-warning">Partial</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div class="action-links">
                                        <a href="view.php?id=<?php echo e($row['id']); ?>" class="btn-info">View</a>
                                        <a href="print.php?id=<?php echo e($row['id']); ?>" target="_blank" class="btn-secondary">Print</a>
                                        <?php if ($balance > 0): ?>
                                            <a href="record_payment.php?id=<?php echo e($row['id']); ?>" class="btn-warning" style="font-size: 11px;">+ Pay</a>
                                        <?php endif; ?>
                                    </div>
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
