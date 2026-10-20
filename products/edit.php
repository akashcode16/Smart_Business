<?php
/**
 * Product Management - Edit Product
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();
$page_title = 'Edit Product';
$active_menu = 'products';

$error_message = '';
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', "Invalid product specified.");
    header("Location: index.php");
    exit;
}

// Fetch product
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ? LIMIT 1");
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

// Fetch categories
$cats_res = mysqli_query($conn, "SELECT id, category_name FROM categories WHERE status = 'Active' OR id = {$product['category_id']} ORDER BY category_name ASC");
$categories_list = [];
while ($c = mysqli_fetch_assoc($cats_res)) {
    $categories_list[] = $c;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $product_id = strtoupper(trim($_POST['product_id'] ?? ''));
        $product_name = trim($_POST['product_name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $brand = trim($_POST['brand'] ?? '');
        $unit = trim($_POST['unit'] ?? 'Piece');
        $purchase_price = (float)($_POST['purchase_price'] ?? 0);
        $selling_price = (float)($_POST['selling_price'] ?? 0);
        $minimum_stock_level = (int)($_POST['minimum_stock_level'] ?? 5);
        $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

        if (empty($product_id) || empty($product_name) || empty($category_id)) {
            $error_message = "Product Code/SKU, Product Name and Category are required.";
        } elseif ($purchase_price < 0 || $selling_price < 0) {
            $error_message = "Prices cannot be negative values.";
        } elseif ($minimum_stock_level < 0) {
            $error_message = "Minimum stock level cannot be negative.";
        } else {
            // Check SKU uniqueness excluding current product
            $chk = mysqli_prepare($conn, "SELECT id FROM products WHERE product_id = ? AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($chk, "si", $product_id, $id);
            mysqli_stmt_execute($chk);
            $res = mysqli_stmt_get_result($chk);

            if (mysqli_num_rows($res) > 0) {
                $error_message = "Another product already uses SKU / Code '{$product_id}'.";
                mysqli_stmt_close($chk);
            } else {
                mysqli_stmt_close($chk);

                $update = mysqli_prepare($conn, "UPDATE products 
                                                 SET product_id = ?, product_name = ?, category_id = ?, brand = ?, unit = ?, purchase_price = ?, selling_price = ?, minimum_stock_level = ?, status = ? 
                                                 WHERE id = ?");
                mysqli_stmt_bind_param($update, "ssissddisi", $product_id, $product_name, $category_id, $brand, $unit, $purchase_price, $selling_price, $minimum_stock_level, $status, $id);
                
                if (mysqli_stmt_execute($update)) {
                    mysqli_stmt_close($update);
                    set_flash('success', "Product '{$product_name}' ({$product_id}) updated successfully.");
                    header("Location: index.php");
                    exit;
                } else {
                    $error_message = "Failed to update product: " . mysqli_error($conn);
                    mysqli_stmt_close($update);
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Edit Product: <?php echo e($product['product_id']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Products / Edit</div>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Products</a>
    </div>
</div>

<div class="page-content">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo e($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 750px;">
        <div class="card-header">
            <span>Edit Specifications &bull; <?php echo e($product['product_id']); ?></span>
        </div>
        <div class="card-body">
            <form action="edit.php?id=<?php echo e($id); ?>" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="product_id">Product Code / SKU <span style="color:#c9302c;">*</span></label>
                            <input type="text" id="product_id" name="product_id" class="form-control" 
                                   value="<?php echo e($_POST['product_id'] ?? $product['product_id']); ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="category_id">Category <span style="color:#c9302c;">*</span></label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories_list as $cat): ?>
                                    <option value="<?php echo e($cat['id']); ?>" <?php echo ((string)($_POST['category_id'] ?? $product['category_id']) === (string)$cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="product_name">Product Name <span style="color:#c9302c;">*</span></label>
                    <input type="text" id="product_name" name="product_name" class="form-control" 
                           value="<?php echo e($_POST['product_name'] ?? $product['product_name']); ?>" required>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="brand">Brand / Manufacturer</label>
                            <input type="text" id="brand" name="brand" class="form-control" 
                                   value="<?php echo e($_POST['brand'] ?? $product['brand']); ?>">
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="unit">Unit of Measurement <span style="color:#c9302c;">*</span></label>
                            <select id="unit" name="unit" class="form-control" required>
                                <?php
                                $units = ['Piece', 'Box', 'Carton', 'Packet', 'Kg', 'Gram', 'Liter', 'Meter', 'Set', 'Bottle', 'Dozen'];
                                foreach ($units as $u) {
                                    $sel = (($_POST['unit'] ?? $product['unit']) === $u) ? 'selected' : '';
                                    echo "<option value=\"{$u}\" {$sel}>{$u}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="purchase_price">Cost / Purchase Price (₹) <span style="color:#c9302c;">*</span></label>
                            <input type="number" step="0.01" min="0" id="purchase_price" name="purchase_price" class="form-control" 
                                   value="<?php echo e($_POST['purchase_price'] ?? $product['purchase_price']); ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="selling_price">Selling Price (₹) <span style="color:#c9302c;">*</span></label>
                            <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" class="form-control" 
                                   value="<?php echo e($_POST['selling_price'] ?? $product['selling_price']); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="minimum_stock_level">Low Stock Alert Level <span style="color:#c9302c;">*</span></label>
                            <input type="number" min="0" id="minimum_stock_level" name="minimum_stock_level" class="form-control" 
                                   value="<?php echo e($_POST['minimum_stock_level'] ?? $product['minimum_stock_level']); ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="status">Product Status <span style="color:#c9302c;">*</span></label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="Active" <?php echo (($_POST['status'] ?? $product['status']) === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo (($_POST['status'] ?? $product['status']) === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Update Product</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
