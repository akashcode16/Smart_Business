<?php
/**
 * Reports - Customer Activity & Balances
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Customer Ledger Report';
$active_menu = 'reports';

$search = trim($_GET['search'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

if (!is_admin()) {
    $where_clauses[] = "(c.created_by = ? OR c.created_by IS NULL)";
    $params[] = (int)$current_user['id'];
    $types .= 'i';
}

if (!empty($search)) {
    $where_clauses[] = "(c.customer_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT c.*, 
          COUNT(s.id) as total_invoices,
          COALESCE(SUM(s.total_amount), 0) as lifetime_billed,
          COALESCE(SUM(p.amount), 0) as lifetime_paid 
          FROM customers c 
          LEFT JOIN sales s ON c.id = s.customer_id 
          LEFT JOIN payments p ON p.transaction_type = 'Sale' AND p.reference_id = s.sale_id 
          {$where_sql} 
          GROUP BY c.id 
          ORDER BY lifetime_billed DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$tot_customers = 0;
$tot_billed = 0.00;
$tot_paid = 0.00;
$rows = [];

while ($r = mysqli_fetch_assoc($result)) {
    $rows[] = $r;
    $tot_customers++;
    $tot_billed += (float)$r['lifetime_billed'];
    $tot_paid += (float)$r['lifetime_paid'];
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Customer Sales Analytics</h1>
        <div class="page-breadcrumb">Smart Business / Reports / Customers</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print()" class="btn btn-secondary btn-sm">&#128438; Print Report</button>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Reports</a>
    </div>
</div>

<div class="page-content">
    <div class="stat-card-row">
        <div class="stat-card stat-purple">
            <div class="stat-card-title">Registered Customers</div>
            <div class="stat-card-value"><?php echo $tot_customers; ?></div>
            <div class="stat-card-sub">Active buyer accounts</div>
        </div>
        <div class="stat-card stat-green">
            <div class="stat-card-title">Lifetime Billed Revenue</div>
            <div class="stat-card-value">₹<?php echo number_format($tot_billed, 2); ?></div>
            <div class="stat-card-sub">Gross sales generated</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">Collections Settled</div>
            <div class="stat-card-value">₹<?php echo number_format($tot_paid, 2); ?></div>
            <div class="stat-card-sub">Total receipts recorded</div>
        </div>
        <div class="stat-card <?php echo ($tot_billed > $tot_paid) ? 'stat-red' : ''; ?>">
            <div class="stat-card-title">Outstanding Credit Due</div>
            <div class="stat-card-value">₹<?php echo number_format(max(0, $tot_billed - $tot_paid), 2); ?></div>
            <div class="stat-card-sub">Uncollected customer balance</div>
        </div>
    </div>

    <div class="filter-bar no-print">
        <form method="GET" action="customers.php" class="filter-form">
            <input type="text" name="search" placeholder="Search customer name, phone, email..." value="<?php echo e($search); ?>" style="min-width: 250px;">
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
            <?php if (!empty($search)): ?>
                <a href="customers.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Customer Sales Ledger</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($rows)): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    No customer transaction records found.
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 100px;">Customer ID</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th style="text-align: right; width: 100px;">Invoices</th>
                            <th style="text-align: right; width: 130px;">Total Billed (₹)</th>
                            <th style="text-align: right; width: 130px;">Total Paid (₹)</th>
                            <th style="text-align: right; width: 130px;">Balance Due (₹)</th>
                            <th style="width: 90px; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): 
                            $bal = max(0, $r['lifetime_billed'] - $r['lifetime_paid']);
                        ?>
                            <tr>
                                <td><strong><?php echo e($r['customer_id']); ?></strong></td>
                                <td>
                                    <a href="../customers/view.php?id=<?php echo e($r['id']); ?>" style="color:#2b5797; font-weight:bold; text-decoration:none;">
                                        <?php echo e($r['customer_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo e($r['phone']); ?></td>
                                <td><?php echo e($r['email'] ?? '—'); ?></td>
                                <td style="text-align: right;"><?php echo (int)$r['total_invoices']; ?></td>
                                <td style="text-align: right; font-weight: bold; color: #1a365d;">₹<?php echo number_format($r['lifetime_billed'], 2); ?></td>
                                <td style="text-align: right; color: #2e7d32; font-weight: bold;">₹<?php echo number_format($r['lifetime_paid'], 2); ?></td>
                                <td style="text-align: right; <?php echo ($bal > 0) ? 'color: #c9302c; font-weight: bold;' : 'color: #777;'; ?>">
                                    ₹<?php echo number_format($bal, 2); ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($r['status'] === 'Active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
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
if ($stmt) mysqli_stmt_close($stmt);
include '../includes/footer.php';
?>
