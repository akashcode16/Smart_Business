<?php
/**
 * Stock Transfers - Initiate Stock Transfer
 * Smart Business Management System
 * Distribution Flow: Stockiest -> Distributor -> Retailer
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Allowed for Admin, Stockiest, and Distributor. Retailer cannot initiate transfers.
require_role(['Admin', 'Stockiest', 'Distributor']);

$current_user = get_logged_in_user();
$page_title = 'Transfer Stock';
$active_menu = 'transfers';

$error_message = '';

// Generate Transfer ID
$trf_q = mysqli_query($conn, "SELECT transfer_id FROM stock_transfers WHERE transfer_id LIKE 'TRF%' ORDER BY id DESC LIMIT 1");
$next_trf = 1;
if ($trf_q && $row = mysqli_fetch_assoc($trf_q)) {
    $next_trf = (int)substr($row['transfer_id'], 3) + 1;
}
$suggested_transfer_id = sprintf("TRF%03d", $next_trf);

// Determine Sender and Eligible Destination Roles
$user_role = $current_user['role_name'];
$sender_id = (int)$current_user['id'];

// If Admin, can select sender
$senders_list = [];
if (is_admin()) {
    $s_res = mysqli_query($conn, "SELECT u.id, u.user_id, u.full_name, r.role_name 
                                  FROM users u 
                                  JOIN roles r ON u.role_id = r.id 
                                  WHERE r.role_name IN ('Stockiest', 'Distributor') AND u.status = 'Active' 
                                  ORDER BY r.id, u.full_name");
    while ($s = mysqli_fetch_assoc($s_res)) {
        $senders_list[] = $s;
    }
    if (isset($_POST['sender_id'])) {
        $sender_id = (int)$_POST['sender_id'];
    } elseif (!empty($senders_list)) {
        $sender_id = (int)$senders_list[0]['id'];
    }
}

// Get sender's role name
$sender_role_res = mysqli_query($conn, "SELECT r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = {$sender_id} LIMIT 1");
$sender_role_name = mysqli_fetch_assoc($sender_role_res)['role_name'] ?? 'Stockiest';

// Determine destination role based on workflow:
// Stockiest -> Distributor
// Distributor -> Retailer
$target_dest_role = ($sender_role_name === 'Stockiest') ? 'Distributor' : 'Retailer';

// Fetch destinations with appropriate role
$dest_res = mysqli_query($conn, "SELECT u.id, u.user_id, u.full_name, r.role_name 
                                 FROM users u 
                                 JOIN roles r ON u.role_id = r.id 
                                 WHERE r.role_name = '{$target_dest_role}' AND u.status = 'Active' AND u.id != {$sender_id} 
                                 ORDER BY u.full_name ASC");
$destinations = [];
while ($d = mysqli_fetch_assoc($dest_res)) {
    $destinations[] = $d;
}

// Fetch available products held by sender with quantity > 0
$avail_res = mysqli_query($conn, "SELECT s.product_id, s.quantity as available_qty, p.product_id as sku, p.product_name, p.unit 
                                  FROM stock s 
                                  JOIN products p ON s.product_id = p.id 
                                  WHERE s.user_id = {$sender_id} AND s.quantity > 0 
                                  ORDER BY p.product_name ASC");
$available_products = [];
while ($ap = mysqli_fetch_assoc($avail_res)) {
    $available_products[] = $ap;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $transfer_id = strtoupper(trim($_POST['transfer_id'] ?? ''));
        $actual_sender_id = is_admin() ? (int)($_POST['sender_id'] ?? 0) : (int)$current_user['id'];
        $to_user_id = (int)($_POST['to_user_id'] ?? 0);
        $product_id = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 0);
        $transfer_date = trim($_POST['transfer_date'] ?? date('Y-m-d'));
        $remarks = trim($_POST['remarks'] ?? '');

        if (empty($transfer_id) || $actual_sender_id <= 0 || $to_user_id <= 0 || $product_id <= 0 || $quantity <= 0) {
            $error_message = "Please fill in all required fields. Transfer quantity must be greater than zero.";
        } elseif ($actual_sender_id === $to_user_id) {
            $error_message = "Sender and destination accounts cannot be the same.";
        } else {
            // Verify destination role matches workflow
            $dest_check = mysqli_query($conn, "SELECT u.id, u.full_name, u.status, r.role_name 
                                               FROM users u 
                                               JOIN roles r ON u.role_id = r.id 
                                               WHERE u.id = {$to_user_id} LIMIT 1");
            $dest_user = mysqli_fetch_assoc($dest_check);

            $sender_check = mysqli_query($conn, "SELECT u.id, u.full_name, u.status, r.role_name 
                                                 FROM users u 
                                                 JOIN roles r ON u.role_id = r.id 
                                                 WHERE u.id = {$actual_sender_id} LIMIT 1");
            $src_user = mysqli_fetch_assoc($sender_check);

            if (!$dest_user || $dest_user['status'] !== 'Active') {
                $error_message = "The selected destination account is inactive or invalid.";
            } elseif ($src_user['role_name'] === 'Stockiest' && $dest_user['role_name'] !== 'Distributor') {
                $error_message = "Workflow Violation: A Stockiest can only transfer stock to an authorized Distributor.";
            } elseif ($src_user['role_name'] === 'Distributor' && $dest_user['role_name'] !== 'Retailer') {
                $error_message = "Workflow Violation: A Distributor can only transfer stock to an authorized Retailer.";
            } else {
                // Check transfer_id uniqueness
                $id_chk = mysqli_prepare($conn, "SELECT id FROM stock_transfers WHERE transfer_id = ? LIMIT 1");
                mysqli_stmt_bind_param($id_chk, "s", $transfer_id);
                mysqli_stmt_execute($id_chk);
                $id_res = mysqli_stmt_get_result($id_chk);

                if (mysqli_num_rows($id_res) > 0) {
                    $error_message = "Transfer ID '{$transfer_id}' already exists.";
                    mysqli_stmt_close($id_chk);
                } else {
                    mysqli_stmt_close($id_chk);

                    // Execute Database Transaction with Row Locking
                    mysqli_begin_transaction($conn);

                    try {
                        // 1. Lock and verify sender stock
                        $src_stk_stmt = mysqli_prepare($conn, "SELECT id, quantity FROM stock WHERE user_id = ? AND product_id = ? FOR UPDATE");
                        mysqli_stmt_bind_param($src_stk_stmt, "ii", $actual_sender_id, $product_id);
                        mysqli_stmt_execute($src_stk_stmt);
                        $src_stk_res = mysqli_stmt_get_result($src_stk_stmt);
                        $src_stock = mysqli_fetch_assoc($src_stk_res);
                        mysqli_stmt_close($src_stk_stmt);

                        if (!$src_stock || (int)$src_stock['quantity'] < $quantity) {
                            $have = $src_stock ? (int)$src_stock['quantity'] : 0;
                            throw new Exception("Insufficient stock! Available quantity is {$have}, cannot transfer {$quantity}.");
                        }

                        $src_current = (int)$src_stock['quantity'];
                        $src_new = $src_current - $quantity;

                        // Deduct sender stock
                        $deduct = mysqli_prepare($conn, "UPDATE stock SET quantity = ? WHERE id = ?");
                        mysqli_stmt_bind_param($deduct, "ii", $src_new, $src_stock['id']);
                        mysqli_stmt_execute($deduct);
                        mysqli_stmt_close($deduct);

                        // 2. Lock and update/insert receiver stock
                        $dst_stk_stmt = mysqli_prepare($conn, "SELECT id, quantity FROM stock WHERE user_id = ? AND product_id = ? FOR UPDATE");
                        mysqli_stmt_bind_param($dst_stk_stmt, "ii", $to_user_id, $product_id);
                        mysqli_stmt_execute($dst_stk_stmt);
                        $dst_stk_res = mysqli_stmt_get_result($dst_stk_stmt);
                        $dst_stock = mysqli_fetch_assoc($dst_stk_res);
                        mysqli_stmt_close($dst_stk_stmt);

                        $dst_current = $dst_stock ? (int)$dst_stock['quantity'] : 0;
                        $dst_new = $dst_current + $quantity;

                        if ($dst_stock) {
                            $add = mysqli_prepare($conn, "UPDATE stock SET quantity = ? WHERE id = ?");
                            mysqli_stmt_bind_param($add, "ii", $dst_new, $dst_stock['id']);
                            mysqli_stmt_execute($add);
                            mysqli_stmt_close($add);
                        } else {
                            $ins_dst = mysqli_prepare($conn, "INSERT INTO stock (user_id, product_id, quantity) VALUES (?, ?, ?)");
                            mysqli_stmt_bind_param($ins_dst, "iii", $to_user_id, $product_id, $dst_new);
                            mysqli_stmt_execute($ins_dst);
                            mysqli_stmt_close($ins_dst);
                        }

                        // 3. Insert into stock_transfers
                        $ins_trf = mysqli_prepare($conn, "INSERT INTO stock_transfers (transfer_id, from_user_id, to_user_id, product_id, quantity, transfer_date, status, remarks) 
                                                          VALUES (?, ?, ?, ?, ?, ?, 'Completed', ?)");
                        mysqli_stmt_bind_param($ins_trf, "siiiiss", $transfer_id, $actual_sender_id, $to_user_id, $product_id, $quantity, $transfer_date, $remarks);
                        mysqli_stmt_execute($ins_trf);
                        $inserted_trf_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($ins_trf);

                        // 4. Log audit movements for both parties
                        $neg_qty = -$quantity;
                        $sender_note = "Transferred to {$dest_user['full_name']} ({$dest_user['role_name']}) via {$transfer_id}";
                        $m_src = mysqli_prepare($conn, "INSERT INTO stock_movements (user_id, product_id, movement_type, quantity, balance_before, balance_after, reference_id, notes) 
                                                        VALUES (?, ?, 'Transfer Out', ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($m_src, "iiiiiss", $actual_sender_id, $product_id, $neg_qty, $src_current, $src_new, $transfer_id, $sender_note);
                        mysqli_stmt_execute($m_src);
                        mysqli_stmt_close($m_src);

                        $receiver_note = "Received from {$src_user['full_name']} ({$src_user['role_name']}) via {$transfer_id}";
                        $m_dst = mysqli_prepare($conn, "INSERT INTO stock_movements (user_id, product_id, movement_type, quantity, balance_before, balance_after, reference_id, notes) 
                                                        VALUES (?, ?, 'Transfer In', ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($m_dst, "iiiiiss", $to_user_id, $product_id, $quantity, $dst_current, $dst_new, $transfer_id, $receiver_note);
                        mysqli_stmt_execute($m_dst);
                        mysqli_stmt_close($m_dst);

                        // Commit transaction
                        mysqli_commit($conn);

                        set_flash('success', "Stock transfer {$transfer_id} completed successfully! Transferred {$quantity} units to {$dest_user['full_name']}.");
                        header("Location: view.php?id=" . $inserted_trf_id);
                        exit;

                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error_message = $e->getMessage();
                    }
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Initiate Stock Transfer</h1>
        <div class="page-breadcrumb">Smart Business / Stock Transfers / Transfer</div>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Transfers</a>
    </div>
</div>

<div class="page-content">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo e($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Workflow Direction Info Box -->
    <div style="background: #e8f4f8; border: 1px solid #bce8f1; border-left: 4px solid #31708f; padding: 12px 16px; border-radius: 3px; margin-bottom: 20px;">
        <strong>Transfer Routing:</strong>
        Sender: <strong><?php echo e($sender_role_name); ?></strong> &rarr; Destination: <strong><?php echo e($target_dest_role); ?></strong>.
        (Inventory is relocated atomically between party ledgers upon submission).
    </div>

    <div class="card" style="max-width: 680px;">
        <div class="card-header">
            <span>Transfer Specifications</span>
            <span class="badge badge-info">Atomic Balance Transfer</span>
        </div>
        <div class="card-body">
            <form action="create.php" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="transfer_id">Transfer # <span style="color:#c9302c;">*</span></label>
                            <input type="text" id="transfer_id" name="transfer_id" class="form-control" 
                                   value="<?php echo isset($_POST['transfer_id']) ? e($_POST['transfer_id']) : e($suggested_transfer_id); ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="transfer_date">Transfer Date <span style="color:#c9302c;">*</span></label>
                            <input type="date" id="transfer_date" name="transfer_date" class="form-control" 
                                   value="<?php echo isset($_POST['transfer_date']) ? e($_POST['transfer_date']) : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>

                <?php if (is_admin()): ?>
                    <div class="form-group">
                        <label for="sender_id">Source Account (Sender) <span style="color:#c9302c;">*</span></label>
                        <select id="sender_id" name="sender_id" class="form-control" onchange="this.form.submit()" required>
                            <?php foreach ($senders_list as $s): ?>
                                <option value="<?php echo e($s['id']); ?>" <?php echo ((int)$sender_id === (int)$s['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($s['full_name']); ?> (<?php echo e($s['role_name']); ?> - <?php echo e($s['user_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="to_user_id">Destination Account (<?php echo e($target_dest_role); ?>) <span style="color:#c9302c;">*</span></label>
                    <select id="to_user_id" name="to_user_id" class="form-control" required>
                        <option value="">-- Select Destination <?php echo e($target_dest_role); ?> --</option>
                        <?php foreach ($destinations as $d): ?>
                            <option value="<?php echo e($d['id']); ?>" <?php echo ((string)($_POST['to_user_id'] ?? '') === (string)$d['id']) ? 'selected' : ''; ?>>
                                <?php echo e($d['full_name']); ?> (ID: <?php echo e($d['user_id']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($destinations)): ?>
                        <small style="color: #c9302c; font-size: 11px;">No active <?php echo e($target_dest_role); ?> accounts found. Please register a <?php echo e($target_dest_role); ?> first.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="product_id">Product to Transfer <span style="color:#c9302c;">*</span></label>
                    <select id="product_id" name="product_id" class="form-control" required onchange="updateAvailable(this)">
                        <option value="">-- Select Product from Available Stock --</option>
                        <?php foreach ($available_products as $ap): ?>
                            <option value="<?php echo e($ap['product_id']); ?>" data-avail="<?php echo e($ap['available_qty']); ?>" data-unit="<?php echo e($ap['unit']); ?>"
                                    <?php echo ((string)($_POST['product_id'] ?? '') === (string)$ap['product_id']) ? 'selected' : ''; ?>>
                                [<?php echo e($ap['sku']); ?>] <?php echo e($ap['product_name']); ?> &mdash; Available: <?php echo number_format($ap['available_qty']); ?> <?php echo e($ap['unit']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($available_products)): ?>
                        <small style="color: #c9302c; font-size: 11px;">You currently have zero products in stock to transfer.</small>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="quantity">Transfer Quantity <span style="color:#c9302c;">*</span></label>
                    <input type="number" id="quantity" name="quantity" min="1" class="form-control" 
                           placeholder="Enter quantity" 
                           value="<?php echo isset($_POST['quantity']) ? e($_POST['quantity']) : ''; ?>" required>
                    <small id="availHelp" style="color: #2b5797; font-size: 12px; display: block; margin-top: 4px;"></small>
                </div>

                <div class="form-group">
                    <label for="remarks">Transfer Notes / Waybill Remarks</label>
                    <textarea id="remarks" name="remarks" class="form-control" rows="2" 
                              placeholder="e.g. Delivery vehicle #, batch number, dispatch instructions..."><?php echo isset($_POST['remarks']) ? e($_POST['remarks']) : ''; ?></textarea>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" <?php echo (empty($available_products) || empty($destinations)) ? 'disabled' : ''; ?>>
                        &check; Authorize &amp; Dispatch Stock
                    </button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateAvailable(select) {
    const opt = select.options[select.selectedIndex];
    const avail = opt.getAttribute('data-avail');
    const unit = opt.getAttribute('data-unit');
    const help = document.getElementById('availHelp');
    const qtyInput = document.getElementById('quantity');
    if (avail) {
        help.textContent = 'Maximum available in sender warehouse: ' + avail + ' ' + unit;
        qtyInput.max = avail;
    } else {
        help.textContent = '';
        qtyInput.removeAttribute('max');
    }
}
// Trigger on page load if selected
document.addEventListener('DOMContentLoaded', function() {
    const pSelect = document.getElementById('product_id');
    if (pSelect.selectedIndex > 0) {
        updateAvailable(pSelect);
    }
});
</script>

<?php include '../includes/footer.php'; ?>
