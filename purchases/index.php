<?php
/**
 * Purchase Management - Purchases List
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();
$page_title = 'Supplier Purchases';
$active_menu = 'purchases';

$search = trim($_GET['search'] ?? '');
$supplier_filter = trim($_GET['supplier_id'] ?? '');
$status_filter = trim($_GET['payment_status'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

// Role restriction: Stockiest sees their own purchases; Admin sees all
if (!is_admin()) {
    $where_clauses[] = "p.user_id = ?";
    $params[] = (int)$current_user['id'];
    $types .= 'i';
}

if (!empty($search)) {
    $where_clauses[] = "(p.purchase_id LIKE ? OR s.supplier_name LIKE ? OR s.company_name LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if (!empty($supplier_filter)) {
    $where_clauses[] = "p.supplier_id = ?";
    $params[] = (int)$supplier_filter;
    $types .= 'i';
}

if (!empty($status_filter)) {
    $where_clauses[] = "p.payment_status = ?";
    $params[] = $status_filter;
    $types .= 's';
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
          COUNT(pi.id) as item_count, COALESCE(SUM(pi.quantity), 0) as total_units 
          FROM purchases p 
          JOIN suppliers s ON p.supplier_id = s.id 
          JOIN users u ON p.user_id = u.id 
          LEFT JOIN purchase_items pi ON p.id = pi.purchase_id 
          {$where_sql} 
          GROUP BY p.id 
          ORDER BY p.purchase_date DESC, p.id DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch suppliers for filter
$sups_res = mysqli_query($conn, "SELECT id, supplier_name FROM suppliers ORDER BY supplier_name ASC");
$suppliers_list = [];
while ($sup = mysqli_fetch_assoc($sups_res)) {
    $suppliers_list[] = $sup;
}

$flash_messages = get_flash();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Supplier Purchases</h1>
        <div class="page-breadcrumb">Smart Business / Purchases</div>
    </div>
    <div>
        <a href="create.php" class="btn btn-primary">+ New Purchase Entry</a>
    </div>
</div>

<div class="page-content">
    <?php foreach ($flash_messages as $flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endforeach; ?>

    <div class="filter-bar">
        <form method="GET" action="index.php" class="filter-form">
            <input type="text" name="search" placeholder="Search PO #, supplier..." value="<?php echo e($search); ?>" style="min-width: 180px;">
            
            <select name="supplier_id">
                <option value="">-- All Suppliers --</option>
                <?php foreach ($suppliers_list as $sup): ?>
                    <option value="<?php echo e($sup['id']); ?>" <?php echo ((string)$supplier_filter === (string)$sup['id']) ? 'selected' : ''; ?>>
                        <?php echo e($sup['supplier_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="payment_status">
                <option value="">-- Payment Status --</option>
                <option value="Paid" <?php echo ($status_filter === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                <option value="Partial" <?php echo ($status_filter === 'Partial') ? 'selected' : ''; ?>>Partial</option>
                <option value="Unpaid" <?php echo ($status_filter === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
            </select>

            <input type="date" name="from_date" value="<?php echo e($from_date); ?>" title="From Date">
            <input type="date" name="to_date" value="<?php echo e($to_date); ?>" title="To Date">

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($search) || !empty($supplier_filter) || !empty($status_filter) || !empty($from_date) || !empty($to_date)): ?>
                <a href="index.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Purchases: <strong><?php echo mysqli_num_rows($result); ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Purchase Orders</span>
            <span class="badge badge-info">Supplier Inventory Inward</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (mysqli_num_rows($result) === 0): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    <p style="font-size: 15px; margin-bottom: 8px;">No purchase records found.</p>
                    <a href="create.php" class="btn btn-primary btn-sm">+ Create Purchase Entry</a>
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 110px;">Purchase #</th>
                            <th style="width: 100px;">Date</th>
                            <th>Supplier</th>
                            <?php if (is_admin()): ?>
                                <th>Purchaser</th>
                            <?php endif; ?>
                            <th style="text-align: right; width: 90px;">Items</th>
                            <th style="text-align: right; width: 100px;">Units</th>
                            <th style="text-align: right; width: 120px;">Total Amount</th>
                            <th style="width: 100px; text-align: center;">Payment</th>
                            <th style="width: 160px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?php echo e($row['id']); ?>" style="color: #2b5797; font-weight: bold; text-decoration: none;">
                                        <?php echo e($row['purchase_id']); ?>
                                    </a>
                                </td>
                                <td><?php echo date('d M Y', strtotime($row['purchase_date'])); ?></td>
                                <td>
                                    <strong><?php echo e($row['supplier_name']); ?></strong>
                                    <?php if (!empty($row['company_name'])): ?>
                                        <div style="font-size: 11px; color: #777;"><?php echo e($row['company_name']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php if (is_admin()): ?>
                                    <td><?php echo e($row['purchaser_name']); ?></td>
                                <?php endif; ?>
                                <td style="text-align: right;"><?php echo (int)$row['item_count']; ?></td>
                                <td style="text-align: right; font-weight: bold;"><?php echo number_format($row['total_units']); ?></td>
                                <td style="text-align: right; font-weight: bold; color: #1a365d;">₹<?php echo number_format($row['total_amount'], 2); ?></td>
                                <td style="text-align: center;">
                                    <?php if ($row['payment_status'] === 'Paid'): ?>
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
