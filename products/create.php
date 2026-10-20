<?php
/**
 * Product Management - Add Product
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();
$page_title = 'Add Product';
$active_menu = 'products';

$error_message = '';

// Generate suggested SKU
$sku_query = "SELECT product_id FROM products WHERE product_id LIKE 'PRD%' ORDER BY id DESC LIMIT 1";
$sku_res = mysqli_query($conn, $sku_query);
$next_sku_num = 1;
if ($sku_res && $row = mysqli_fetch_assoc($sku_res)) {
    $num = (int)substr($row['product_id'], 3);
    $next_sku_num = $num + 1;
}
$suggested_sku = sprintf("PRD%03d", $next_sku_num);

// Fetch categories
$cats_res = mysqli_query($conn, "SELECT id, category_name FROM categories WHERE status = 'Active' ORDER BY category_name ASC");
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
            // Check SKU uniqueness
            $chk = mysqli_prepare($conn, "SELECT id FROM products WHERE product_id = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, "s", $product_id);
            mysqli_stmt_execute($chk);
            $res = mysqli_stmt_get_result($chk);

            if (mysqli_num_rows($res) > 0) {
                $error_message = "A product with SKU / Code '{$product_id}' already exists.";
                mysqli_stmt_close($chk);
            } else {
                mysqli_stmt_close($chk);

                $insert = mysqli_prepare($conn, "INSERT INTO products (product_id, product_name, category_id, brand, unit, purchase_price, selling_price, minimum_stock_level, status) 
                                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($insert, "ssissddis", $product_id, $product_name, $category_id, $brand, $unit, $purchase_price, $selling_price, $minimum_stock_level, $status);
                
                if (mysqli_stmt_execute($insert)) {
                    mysqli_stmt_close($insert);
                    set_flash('success', "Product '{$product_name}' ({$product_id}) added successfully.");
                    header("Location: index.php");
                    exit;
                } else {
                    $error_message = "Failed to add product: " . mysqli_error($conn);
                    mysqli_stmt_close($insert);
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Add New Product</h1>
        <div class="page-breadcrumb">Smart Business / Products / Add</div>
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
            <span>Product Specifications</span>
        </div>
        <div class="card-body">
            <form action="create.php" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="product_id">Product Code / SKU <span style="color:#c9302c;">*</span></label>
                            <input type="text" id="product_id" name="product_id" class="form-control" 
                                   value="<?php echo isset($_POST['product_id']) ? e($_POST['product_id']) : e($suggested_sku); ?>" required>
                            <small style="color:#666; font-size:11px;">Unique identifier (e.g. PRD001, SKU-1002)</small>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="category_id">Category <span style="color:#c9302c;">*</span></label>
                            <select id="category_id" name="category_id" class="form-control" required>
                                <option value="">-- Select Category --</option>
                                <?php foreach ($categories_list as $cat): ?>
                                    <option value="<?php echo e($cat['id']); ?>" <?php echo ((string)($_POST['category_id'] ?? '') === (string)$cat['id']) ? 'selected' : ''; ?>>
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
                           placeholder="e.g. Premium Cotton Shirt / LED Monitor 24 inch" 
                           value="<?php echo isset($_POST['product_name']) ? e($_POST['product_name']) : ''; ?>" required>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="brand">Brand / Manufacturer</label>
                            <input type="text" id="brand" name="brand" class="form-control" 
                                   placeholder="e.g. Samsung / Nestlé" 
                                   value="<?php echo isset($_POST['brand']) ? e($_POST['brand']) : ''; ?>">
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="unit">Unit of Measurement <span style="color:#c9302c;">*</span></label>
                            <select id="unit" name="unit" class="form-control" required>
                                <?php
                                $units = ['Piece', 'Box', 'Carton', 'Packet', 'Kg', 'Gram', 'Liter', 'Meter', 'Set', 'Bottle', 'Dozen'];
                                foreach ($units as $u) {
                                    $sel = (($_POST['unit'] ?? 'Piece') === $u) ? 'selected' : '';
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
                                   placeholder="0.00" 
                                   value="<?php echo isset($_POST['purchase_price']) ? e($_POST['purchase_price']) : '0.00'; ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="selling_price">Selling Price (₹) <span style="color:#c9302c;">*</span></label>
                            <input type="number" step="0.01" min="0" id="selling_price" name="selling_price" class="form-control" 
                                   placeholder="0.00" 
                                   value="<?php echo isset($_POST['selling_price']) ? e($_POST['selling_price']) : '0.00'; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="minimum_stock_level">Low Stock Alert Level <span style="color:#c9302c;">*</span></label>
                            <input type="number" min="0" id="minimum_stock_level" name="minimum_stock_level" class="form-control" 
                                   placeholder="5" 
                                   value="<?php echo isset($_POST['minimum_stock_level']) ? e($_POST['minimum_stock_level']) : '5'; ?>" required>
                            <small style="color:#666; font-size:11px;">Triggers alert when user stock drops below this number.</small>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="status">Product Status <span style="color:#c9302c;">*</span></label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="Active" <?php echo (($_POST['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo (($_POST['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Save Product</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
