<?php
/**
 * Product Management - Product List
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Allowed for Admin and Stockiest
require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();
$page_title = 'Products Catalog';
$active_menu = 'products';

$search = trim($_GET['search'] ?? '');
$category_filter = trim($_GET['category'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_clauses[] = "(p.product_id LIKE ? OR p.product_name LIKE ? OR p.brand LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if (!empty($category_filter)) {
    $where_clauses[] = "p.category_id = ?";
    $params[] = (int)$category_filter;
    $types .= 'i';
}

if (!empty($status_filter)) {
    $where_clauses[] = "p.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT p.*, c.category_name, 
          COALESCE(SUM(s.quantity), 0) as total_network_stock 
          FROM products p 
          LEFT JOIN categories c ON p.category_id = c.id 
          LEFT JOIN stock s ON p.id = s.product_id 
          {$where_sql} 
          GROUP BY p.id 
          ORDER BY p.product_name ASC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch active categories for filter
$cats_res = mysqli_query($conn, "SELECT id, category_name FROM categories ORDER BY category_name ASC");
$categories_list = [];
while ($c = mysqli_fetch_assoc($cats_res)) {
    $categories_list[] = $c;
}

$flash_messages = get_flash();

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Products Catalog</h1>
        <div class="page-breadcrumb">Smart Business / Products</div>
    </div>
    <div>
        <a href="create.php" class="btn btn-primary">+ Add New Product</a>
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
            <input type="text" name="search" placeholder="Search by name, SKU or brand..." value="<?php echo e($search); ?>" style="min-width: 240px;">
            
            <select name="category">
                <option value="">-- All Categories --</option>
                <?php foreach ($categories_list as $cat): ?>
                    <option value="<?php echo e($cat['id']); ?>" <?php echo ((string)$category_filter === (string)$cat['id']) ? 'selected' : ''; ?>>
                        <?php echo e($cat['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value="">-- All Statuses --</option>
                <option value="Active" <?php echo ($status_filter === 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo ($status_filter === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($search) || !empty($category_filter) || !empty($status_filter)): ?>
                <a href="index.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Total Products: <strong><?php echo mysqli_num_rows($result); ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Product Master List</span>
            <span class="badge badge-info">Pricing &amp; Specification Directory</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (mysqli_num_rows($result) === 0): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    <p style="font-size: 15px; margin-bottom: 8px;">No products found.</p>
                    <a href="create.php" class="btn btn-primary btn-sm">Add First Product</a>
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 100px;">SKU / Code</th>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th style="text-align: right; width: 110px;">Cost Price</th>
                            <th style="text-align: right; width: 110px;">Selling Price</th>
                            <th style="text-align: right; width: 90px;">Min Stock</th>
                            <th style="text-align: right; width: 110px;">Total Stock</th>
                            <th style="width: 80px;">Status</th>
                            <th style="width: 170px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><strong><?php echo e($row['product_id']); ?></strong></td>
                                <td>
                                    <a href="view.php?id=<?php echo e($row['id']); ?>" style="color: #2b5797; font-weight: bold; text-decoration: none;">
                                        <?php echo e($row['product_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo e($row['category_name'] ?? 'Uncategorized'); ?></td>
                                <td><?php echo e($row['brand'] ?? '—'); ?></td>
                                <td style="text-align: right;">₹<?php echo number_format($row['purchase_price'], 2); ?></td>
                                <td style="text-align: right; font-weight: bold; color: #2e7d32;">₹<?php echo number_format($row['selling_price'], 2); ?></td>
                                <td style="text-align: right;"><?php echo e($row['minimum_stock_level']); ?> <?php echo e($row['unit']); ?></td>
                                <td style="text-align: right;">
                                    <strong><?php echo number_format($row['total_network_stock']); ?></strong> <?php echo e($row['unit']); ?>
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
                                            <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Delete this product permanently?');">
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
