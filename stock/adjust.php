<?php
/**
 * Inventory Management - Stock Adjustment with Audit Logging
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Stock Adjustment';
$active_menu = 'stock';

$error_message = '';

$pre_product_id = (int)($_GET['product_id'] ?? 0);
$pre_user_id = is_admin() ? (int)($_GET['user_id'] ?? 0) : (int)$current_user['id'];

// Fetch products
$products_res = mysqli_query($conn, "SELECT id, product_id, product_name, unit FROM products WHERE status = 'Active' ORDER BY product_name ASC");
$products_list = [];
while ($p = mysqli_fetch_assoc($products_res)) {
    $products_list[] = $p;
}

// Fetch users list for Admin
$users_list = [];
if (is_admin()) {
    $u_res = mysqli_query($conn, "SELECT u.id, u.user_id, u.full_name, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name != 'Admin' ORDER BY r.id, u.full_name");
    while ($u = mysqli_fetch_assoc($u_res)) {
        $users_list[] = $u;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $target_user_id = is_admin() ? (int)($_POST['target_user_id'] ?? 0) : (int)$current_user['id'];
        $product_id = (int)($_POST['product_id'] ?? 0);
        $action = $_POST['action'] ?? 'add';
        $adj_qty = (int)($_POST['quantity'] ?? 0);
        $reason = trim($_POST['reason'] ?? 'Inventory Count Correction');
        $notes = trim($_POST['notes'] ?? '');

        if ($target_user_id <= 0 || $product_id <= 0 || $adj_qty <= 0) {
            $error_message = "Please select valid product, account and a quantity greater than zero.";
        } else {
            // Begin database transaction
            mysqli_begin_transaction($conn);

            try {
                // Fetch current stock with row locking
                $stk_stmt = mysqli_prepare($conn, "SELECT id, quantity FROM stock WHERE user_id = ? AND product_id = ? FOR UPDATE");
                mysqli_stmt_bind_param($stk_stmt, "ii", $target_user_id, $product_id);
                mysqli_stmt_execute($stk_stmt);
                $stk_res = mysqli_stmt_get_result($stk_stmt);
                $existing_stock = mysqli_fetch_assoc($stk_res);
                mysqli_stmt_close($stk_stmt);

                $current_qty = $existing_stock ? (int)$existing_stock['quantity'] : 0;
                $change = ($action === 'deduct') ? -$adj_qty : $adj_qty;
                $new_qty = $current_qty + $change;

                // Enforce: Never allow negative stock
                if ($new_qty < 0) {
                    throw new Exception("Insufficient stock! Available quantity is {$current_qty}, cannot deduct {$adj_qty}.");
                }

                // Update or Insert stock record
                if ($existing_stock) {
                    $up_stk = mysqli_prepare($conn, "UPDATE stock SET quantity = ? WHERE id = ?");
                    mysqli_stmt_bind_param($up_stk, "ii", $new_qty, $existing_stock['id']);
                    mysqli_stmt_execute($up_stk);
                    mysqli_stmt_close($up_stk);
                } else {
                    $ins_stk = mysqli_prepare($conn, "INSERT INTO stock (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    mysqli_stmt_bind_param($ins_stk, "iii", $target_user_id, $product_id, $new_qty);
                    mysqli_stmt_execute($ins_stk);
                    mysqli_stmt_close($ins_stk);
                }

                // Log audit movement into stock_movements
                $movement_type = ($reason === 'Opening Stock') ? 'Opening' : 'Adjustment';
                $log_notes = "Adjustment: {$reason}. " . ($notes ? "Notes: {$notes}" : "");
                $mov_stmt = mysqli_prepare($conn, "INSERT INTO stock_movements (user_id, product_id, movement_type, quantity, balance_before, balance_after, reference_id, notes) 
                                                   VALUES (?, ?, ?, ?, ?, ?, 'ADJ-MANUAL', ?)");
                mysqli_stmt_bind_param($mov_stmt, "iisiiis", $target_user_id, $product_id, $movement_type, $change, $current_qty, $new_qty, $log_notes);
                mysqli_stmt_execute($mov_stmt);
                mysqli_stmt_close($mov_stmt);

                // Commit transaction
                mysqli_commit($conn);

                set_flash('success', "Stock adjusted successfully. Previous: {$current_qty}, New Balance: {$new_qty}.");
                header("Location: index.php");
                exit;

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_message = $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Stock Adjustment &amp; Opening Stock</h1>
        <div class="page-breadcrumb">Smart Business / Stock / Adjust</div>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Stock</a>
    </div>
</div>

<div class="page-content">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo e($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 650px;">
        <div class="card-header">
            <span>Adjustment Entry</span>
            <span class="badge badge-warning">Audit Logged</span>
        </div>
        <div class="card-body">
            <form action="adjust.php" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <?php if (is_admin()): ?>
                    <div class="form-group">
                        <label for="target_user_id">Business Account <span style="color:#c9302c;">*</span></label>
                        <select id="target_user_id" name="target_user_id" class="form-control" required>
                            <option value="">-- Select Target Account --</option>
                            <?php foreach ($users_list as $u): ?>
                                <option value="<?php echo e($u['id']); ?>" <?php echo ((string)($_POST['target_user_id'] ?? $pre_user_id) === (string)$u['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($u['full_name']); ?> (<?php echo e($u['role_name']); ?> - <?php echo e($u['user_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="target_user_id" value="<?php echo e($current_user['id']); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="product_id">Select Product <span style="color:#c9302c;">*</span></label>
                    <select id="product_id" name="product_id" class="form-control" required>
                        <option value="">-- Choose Product --</option>
                        <?php foreach ($products_list as $prod): ?>
                            <option value="<?php echo e($prod['id']); ?>" <?php echo ((string)($_POST['product_id'] ?? $pre_product_id) === (string)$prod['id']) ? 'selected' : ''; ?>>
                                [<?php echo e($prod['product_id']); ?>] <?php echo e($prod['product_name']); ?> (Unit: <?php echo e($prod['unit']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="action">Action Type <span style="color:#c9302c;">*</span></label>
                            <select id="action" name="action" class="form-control" required>
                                <option value="add" <?php echo (($_POST['action'] ?? 'add') === 'add') ? 'selected' : ''; ?>>Add Stock (+)</option>
                                <option value="deduct" <?php echo (($_POST['action'] ?? '') === 'deduct') ? 'selected' : ''; ?>>Deduct Stock (-)</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="quantity">Quantity <span style="color:#c9302c;">*</span></label>
                            <input type="number" id="quantity" name="quantity" min="1" class="form-control" 
                                   placeholder="e.g. 10" 
                                   value="<?php echo isset($_POST['quantity']) ? e($_POST['quantity']) : ''; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Adjustment <span style="color:#c9302c;">*</span></label>
                    <select id="reason" name="reason" class="form-control" required>
                        <option value="Opening Stock">Opening Stock Entry</option>
                        <option value="Inventory Count Correction" selected>Physical Inventory Count Correction</option>
                        <option value="Damaged / Spoilage">Damaged / Expired / Spoilage</option>
                        <option value="Customer Return">Customer Return</option>
                        <option value="Other Audit Correction">Other Audit Adjustment</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="notes">Audit Remarks / Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="2" 
                              placeholder="Explanation for audit records..."><?php echo isset($_POST['notes']) ? e($_POST['notes']) : ''; ?></textarea>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-warning">Apply Adjustment</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
