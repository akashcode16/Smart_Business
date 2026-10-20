<?php
/**
 * Product Management - View Product Details
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Product Details';
$active_menu = 'products';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', "Invalid product specified.");
    header("Location: index.php");
    exit;
}

// Fetch product details
$stmt = mysqli_prepare($conn, "SELECT p.*, c.category_name 
                               FROM products p 
                               LEFT JOIN categories c ON p.category_id = c.id 
                               WHERE p.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$product) {
    set_flash('danger', "Product not found.");
    header("Location: index.php");
    exit;
}

// Calculate profit margin percentage
$cost = (float)$product['purchase_price'];
$selling = (float)$product['selling_price'];
$margin_pct = ($cost > 0) ? (($selling - $cost) / $cost) * 100 : 0;

// Fetch inventory breakdown across business entities
$stock_query = "SELECT s.quantity, s.updated_at, u.user_id, u.full_name, r.role_name 
                FROM stock s 
                JOIN users u ON s.user_id = u.id 
                JOIN roles r ON u.role_id = r.id 
                WHERE s.product_id = ? AND s.quantity > 0 
                ORDER BY r.id ASC, s.quantity DESC";
$st_stmt = mysqli_prepare($conn, $stock_query);
mysqli_stmt_bind_param($st_stmt, "i", $id);
mysqli_stmt_execute($st_stmt);
$stock_breakdown = mysqli_stmt_get_result($st_stmt);

$total_units = 0;

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Product: <?php echo e($product['product_name']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Products / Details</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <?php if (is_admin() || is_stockiest()): ?>
            <a href="edit.php?id=<?php echo e($product['id']); ?>" class="btn btn-primary btn-sm">Edit Product</a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Products</a>
    </div>
</div>

<div class="page-content">
    <div class="grid-row">
        <!-- Product Info Card -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Product Specifications</span>
                    <span class="badge badge-info"><?php echo e($product['product_id']); ?></span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tr>
                            <th style="width: 160px;">Product Code / SKU</th>
                            <td><strong><?php echo e($product['product_id']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Product Name</th>
                            <td><?php echo e($product['product_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Category</th>
                            <td><?php echo e($product['category_name'] ?? 'Uncategorized'); ?></td>
                        </tr>
                        <tr>
                            <th>Brand / Manufacturer</th>
                            <td><?php echo e($product['brand'] ?? '—'); ?></td>
                        </tr>
                        <tr>
                            <th>Unit of Measure</th>
                            <td><?php echo e($product['unit']); ?></td>
                        </tr>
                        <tr>
                            <th>Cost / Purchase Price</th>
                            <td>₹<?php echo number_format($product['purchase_price'], 2); ?></td>
                        </tr>
                        <tr>
                            <th>Selling Price</th>
                            <td><strong style="color: #2e7d32;">₹<?php echo number_format($product['selling_price'], 2); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Margin Markup</th>
                            <td>
                                <?php if ($margin_pct >= 0): ?>
                                    <span style="color: #2e7d32; font-weight: bold;">+<?php echo number_format($margin_pct, 1); ?>%</span>
                                <?php else: ?>
                                    <span style="color: #c9302c; font-weight: bold;"><?php echo number_format($margin_pct, 1); ?>%</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Low Stock Alert Level</th>
                            <td><?php echo e($product['minimum_stock_level']); ?> <?php echo e($product['unit']); ?></td>
                        </tr>
                        <tr>
                            <th>Catalog Status</th>
                            <td>
                                <?php if ($product['status'] === 'Active'): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Created Date</th>
                            <td><?php echo date('d M Y, h:i A', strtotime($product['created_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Distribution & Stock Breakdown Card -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Supply Chain Stock Distribution</span>
                    <span style="font-size: 12px; color: #666;">Stockiest &rarr; Distributor &rarr; Retailer</span>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($stock_breakdown) === 0): ?>
                        <div style="padding: 25px; text-align: center; color: #888;">
                            <p style="font-size: 14px; margin-bottom: 5px;">Currently zero units in stock across all parties.</p>
                            <small>Stockiest can purchase inventory from Suppliers to initiate stock.</small>
                        </div>
                    <?php else: ?>
                        <table class="data-table" style="margin-top: 0;">
                            <thead>
                                <tr>
                                    <th>Holding Business Entity</th>
                                    <th>Role</th>
                                    <th style="text-align: right; width: 100px;">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($st = mysqli_fetch_assoc($stock_breakdown)): 
                                    $total_units += (int)$st['quantity'];
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($st['full_name']); ?></strong>
                                            <div style="font-size: 11px; color: #777;">ID: <?php echo e($st['user_id']); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge-role badge-<?php echo strtolower($st['role_name']); ?>">
                                                <?php echo e($st['role_name']); ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <strong><?php echo number_format($st['quantity']); ?></strong> <?php echo e($product['unit']); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                <tr style="background: #f8fafc; font-weight: bold;">
                                    <td colspan="2">Total Network Stock</td>
                                    <td style="text-align: right; color: #1a365d;">
                                        <?php echo number_format($total_units); ?> <?php echo e($product['unit']); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
if ($st_stmt) {
    mysqli_stmt_close($st_stmt);
}
include '../includes/footer.php';
?>
