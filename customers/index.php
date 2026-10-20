<?php
/**
 * Customer Management - Customer Directory
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Allowed for Admin, Distributor, and Retailer
require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Customers';
$active_menu = 'customers';

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

// Role-based data access restriction
if (!is_admin()) {
    $where_clauses[] = "(c.created_by = ? OR c.created_by IS NULL)";
    $params[] = (int)$current_user['id'];
    $types .= 'i';
}

if (!empty($search)) {
    $where_clauses[] = "(c.customer_id LIKE ? OR c.customer_name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

if (!empty($status_filter)) {
    $where_clauses[] = "c.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT c.*, u.full_name as created_by_name, u.user_id as creator_user_id,
          COUNT(s.id) as total_orders, COALESCE(SUM(s.total_amount), 0) as total_spend 
          FROM customers c 
          LEFT JOIN users u ON c.created_by = u.id 
          LEFT JOIN sales s ON c.id = s.customer_id 
          {$where_sql} 
          GROUP BY c.id 
          ORDER BY c.customer_name ASC";

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
        <h1>Customers Directory</h1>
        <div class="page-breadcrumb">Smart Business / Customers</div>
    </div>
    <div>
        <a href="create.php" class="btn btn-primary">+ Add New Customer</a>
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
            <input type="text" name="search" placeholder="Search customer name, phone, email, ID..." value="<?php echo e($search); ?>" style="min-width: 260px;">
            
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
            Customers: <strong><?php echo mysqli_num_rows($result); ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Customer Accounts</span>
            <span class="badge badge-info">
                <?php echo is_admin() ? 'All Enterprise Customers' : 'My Registered Customers'; ?>
            </span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (mysqli_num_rows($result) === 0): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    <p style="font-size: 15px; margin-bottom: 8px;">No customers registered yet.</p>
                    <a href="create.php" class="btn btn-primary btn-sm">Register First Customer</a>
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 90px;">Customer ID</th>
                            <th>Customer Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <?php if (is_admin()): ?>
                                <th>Managed By</th>
                            <?php endif; ?>
                            <th style="text-align: right; width: 130px;">Total Purchases</th>
                            <th style="width: 80px;">Status</th>
                            <th style="width: 170px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><strong><?php echo e($row['customer_id']); ?></strong></td>
                                <td>
                                    <a href="view.php?id=<?php echo e($row['id']); ?>" style="color: #2b5797; font-weight: bold; text-decoration: none;">
                                        <?php echo e($row['customer_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo e($row['phone']); ?></td>
                                <td><?php echo e($row['email'] ?? '—'); ?></td>
                                <?php if (is_admin()): ?>
                                    <td>
                                        <?php if (!empty($row['created_by_name'])): ?>
                                            <?php echo e($row['created_by_name']); ?> <span style="font-size: 11px; color:#777;">(<?php echo e($row['creator_user_id']); ?>)</span>
                                        <?php else: ?>
                                            <span style="color: #888;">Direct / Walk-in</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td style="text-align: right;">
                                    <strong>₹<?php echo number_format($row['total_spend'], 2); ?></strong>
                                    <div style="font-size: 11px; color: #666;"><?php echo (int)$row['total_orders']; ?> invoice(s)</div>
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
                                        
                                        <!-- Delete customer if allowed -->
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this customer record?');">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">
                                            <button type="submit" class="btn-danger" style="font-size: 11px;">Delete</button>
                                        </form>
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
