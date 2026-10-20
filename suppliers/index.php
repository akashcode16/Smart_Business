<?php
/**
 * Supplier Management - Supplier List
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Allowed for Admin and Stockiest
require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();
$page_title = 'Suppliers Directory';
$active_menu = 'suppliers';

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_clauses[] = "(s.supplier_id LIKE ? OR s.supplier_name LIKE ? OR s.company_name LIKE ? OR s.phone LIKE ? OR s.email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sssss';
}

if (!empty($status_filter)) {
    $where_clauses[] = "s.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT s.*, COUNT(p.id) as total_purchases, COALESCE(SUM(p.total_amount), 0) as total_purchased_value 
          FROM suppliers s 
          LEFT JOIN purchases p ON s.id = p.supplier_id 
          {$where_sql} 
          GROUP BY s.id 
          ORDER BY s.supplier_name ASC";

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
        <h1>Suppliers Directory</h1>
        <div class="page-breadcrumb">Smart Business / Suppliers</div>
    </div>
    <div>
        <a href="create.php" class="btn btn-primary">+ Add New Supplier</a>
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
            <input type="text" name="search" placeholder="Search by name, company, phone, email..." value="<?php echo e($search); ?>" style="min-width: 260px;">
            
            <select name="status">
                <option value="">-- All Statuses --</option>
                <option value="Active" <?php echo ($status_filter === 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo ($status_filter === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($search) || !empty($status_filter)): ?>
                <a href="index.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Suppliers: <strong><?php echo mysqli_num_rows($result); ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Supplier Partners</span>
            <span class="badge badge-info">Vendor Directory</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (mysqli_num_rows($result) === 0): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    <p style="font-size: 15px; margin-bottom: 8px;">No suppliers found in system.</p>
                    <a href="create.php" class="btn btn-primary btn-sm">Add First Supplier</a>
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 90px;">Supplier ID</th>
                            <th>Contact Person</th>
                            <th>Company Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th style="text-align: right; width: 130px;">Total Purchases</th>
                            <th style="width: 80px;">Status</th>
                            <th style="width: 170px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><strong><?php echo e($row['supplier_id']); ?></strong></td>
                                <td>
                                    <a href="view.php?id=<?php echo e($row['id']); ?>" style="color: #2b5797; font-weight: bold; text-decoration: none;">
                                        <?php echo e($row['supplier_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo e($row['company_name'] ?? '—'); ?></td>
                                <td><?php echo e($row['phone']); ?></td>
                                <td><?php echo e($row['email'] ?? '—'); ?></td>
                                <td style="text-align: right;">
                                    <strong>₹<?php echo number_format($row['total_purchased_value'], 2); ?></strong>
                                    <div style="font-size: 11px; color: #666;"><?php echo (int)$row['total_purchases']; ?> order(s)</div>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'Active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <div class="action-links">
                                        <a href="view.php?id=<?php echo e($row['id']); ?>" class="btn-info">View</a>
                                        <a href="edit.php?id=<?php echo e($row['id']); ?>" class="btn-secondary">Edit</a>
                                        
                                        <?php if (is_admin()): ?>
                                            <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this supplier?');">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">
                                                <button type="submit" class="btn-danger" style="font-size: 11px;">Delete</button>
                                            </form>
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
