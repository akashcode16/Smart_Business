<?php
/**
 * Category Management - Category List
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Strict Role Check - Only Admin allowed
require_role('Admin');

$current_user = get_logged_in_user();
$page_title = 'Category Management';
$active_menu = 'categories';

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_clauses[] = "(c.category_name LIKE ? OR c.description LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if (!empty($status_filter)) {
    $where_clauses[] = "c.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT c.*, COUNT(p.id) as product_count 
          FROM categories c 
          LEFT JOIN products p ON c.id = p.category_id 
          {$where_sql} 
          GROUP BY c.id 
          ORDER BY c.category_name ASC";

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
        <h1>Category Management</h1>
        <div class="page-breadcrumb">Smart Business / Admin / Categories</div>
    </div>
    <div>
        <a href="create.php" class="btn btn-primary">+ Add New Category</a>
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
            <input type="text" name="search" placeholder="Search categories..." value="<?php echo e($search); ?>" style="min-width: 240px;">
            
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
            Total Categories: <strong><?php echo mysqli_num_rows($result); ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Product Categories</span>
            <span class="badge badge-info">Classification Directory</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (mysqli_num_rows($result) === 0): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    <p style="font-size: 15px; margin-bottom: 8px;">No categories found.</p>
                    <a href="create.php" class="btn btn-primary btn-sm">Add First Category</a>
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th style="width: 220px;">Category Name</th>
                            <th>Description</th>
                            <th style="width: 130px; text-align: center;">Linked Products</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 140px;">Created Date</th>
                            <th style="width: 200px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><?php echo e($row['id']); ?></td>
                                <td><strong><?php echo e($row['category_name']); ?></strong></td>
                                <td><?php echo e($row['description'] ?? '—'); ?></td>
                                <td style="text-align: center;">
                                    <span class="badge badge-info"><?php echo (int)$row['product_count']; ?> Products</span>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'Active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                                <td style="text-align: center;">
                                    <div class="action-links">
                                        <a href="edit.php?id=<?php echo e($row['id']); ?>" class="btn-secondary">Edit</a>
                                        
                                        <!-- Toggle Status -->
                                        <form method="POST" action="toggle_status.php" style="display:inline;" onsubmit="return confirm('Change status for this category?');">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">
                                            <button type="submit" class="<?php echo ($row['status'] === 'Active') ? 'btn-warning' : 'btn-success'; ?>" style="font-size: 11px;">
                                                <?php echo ($row['status'] === 'Active') ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>

                                        <!-- Safe Delete -->
                                        <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
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
