<?php
/**
 * Purchase Management - Create Purchase Order
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();
$page_title = 'New Purchase Entry';
$active_menu = 'purchases';

$error_message = '';

// Generate next Purchase ID (e.g. PUR001)
$pur_query = "SELECT purchase_id FROM purchases WHERE purchase_id LIKE 'PUR%' ORDER BY id DESC LIMIT 1";
$pur_res = mysqli_query($conn, $pur_query);
$next_num = 1;
if ($pur_res && $row = mysqli_fetch_assoc($pur_res)) {
    $next_num = (int)substr($row['purchase_id'], 3) + 1;
}
$suggested_purchase_id = sprintf("PUR%03d", $next_num);

// Fetch suppliers
$sups_res = mysqli_query($conn, "SELECT id, supplier_name, company_name FROM suppliers WHERE status = 'Active' ORDER BY supplier_name ASC");
$suppliers = [];
while ($s = mysqli_fetch_assoc($sups_res)) {
    $suppliers[] = $s;
}

// Fetch products
$prods_res = mysqli_query($conn, "SELECT id, product_id, product_name, unit, purchase_price FROM products WHERE status = 'Active' ORDER BY product_name ASC");
$products = [];
while ($p = mysqli_fetch_assoc($prods_res)) {
    $products[] = $p;
}

// Fetch active stockiests (for Admin assignment to central warehouse)
$stockiests = [];
$stk_users_res = mysqli_query($conn, "SELECT u.id, u.user_id, u.full_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'Stockiest' AND u.status = 'Active' ORDER BY u.full_name ASC");
while ($su = mysqli_fetch_assoc($stk_users_res)) {
    $stockiests[] = $su;
}

// Business purchaser: If Stockiest, current user. If Admin, select from dropdown or default to primary Stockiest
$purchaser_id = (int)$current_user['id'];
if (is_admin()) {
    if (isset($_POST['stockiest_id']) && (int)$_POST['stockiest_id'] > 0) {
        $purchaser_id = (int)$_POST['stockiest_id'];
    } elseif (!empty($stockiests)) {
        $purchaser_id = (int)$stockiests[0]['id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $purchase_id = strtoupper(trim($_POST['purchase_id'] ?? ''));
        $supplier_id = (int)($_POST['supplier_id'] ?? 0);
        $purchase_date = trim($_POST['purchase_date'] ?? date('Y-m-d'));
        $payment_status = in_array($_POST['payment_status'] ?? '', ['Paid', 'Partial', 'Unpaid']) ? $_POST['payment_status'] : 'Paid';
        $payment_method = in_array($_POST['payment_method'] ?? '', ['Cash', 'Card', 'UPI', 'Bank Transfer', 'Credit']) ? $_POST['payment_method'] : 'Bank Transfer';
        $notes = trim($_POST['notes'] ?? '');
        
        $item_product_ids = $_POST['product_ids'] ?? [];
        $item_quantities = $_POST['quantities'] ?? [];
        $item_prices = $_POST['prices'] ?? [];

        if (empty($purchase_id) || empty($supplier_id) || empty($purchase_date)) {
            $error_message = "Purchase ID, Supplier, and Date are required.";
        } elseif (empty($item_product_ids) || !is_array($item_product_ids)) {
            $error_message = "Please add at least one product item to this purchase.";
        } else {
            // Check unique purchase_id
            $chk = mysqli_prepare($conn, "SELECT id FROM purchases WHERE purchase_id = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, "s", $purchase_id);
            mysqli_stmt_execute($chk);
            $res = mysqli_stmt_get_result($chk);

            if (mysqli_num_rows($res) > 0) {
                $error_message = "Purchase ID '{$purchase_id}' already exists.";
                mysqli_stmt_close($chk);
            } else {
                mysqli_stmt_close($chk);

                // Validate supplier exists and is active
                $chk_sup = mysqli_prepare($conn, "SELECT id, supplier_name FROM suppliers WHERE id = ? AND status = 'Active' LIMIT 1");
                mysqli_stmt_bind_param($chk_sup, "i", $supplier_id);
                mysqli_stmt_execute($chk_sup);
                $res_sup = mysqli_stmt_get_result($chk_sup);
                if (mysqli_num_rows($res_sup) === 0) {
                    $error_message = "Selected supplier does not exist or is inactive.";
                }
                mysqli_stmt_close($chk_sup);

                // If Admin, validate selected stockiest exists
                if (empty($error_message) && is_admin()) {
                    $chk_stk = mysqli_prepare($conn, "SELECT u.id FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? AND r.role_name = 'Stockiest' AND u.status = 'Active' LIMIT 1");
                    mysqli_stmt_bind_param($chk_stk, "i", $purchaser_id);
                    mysqli_stmt_execute($chk_stk);
                    $res_stk = mysqli_stmt_get_result($chk_stk);
                    if (mysqli_num_rows($res_stk) === 0) {
                        $error_message = "Selected Stockiest warehouse is invalid or inactive.";
                    }
                    mysqli_stmt_close($chk_stk);
                }

                // Validate and calculate line items
                $clean_items = [];
                $grand_total = 0.00;

                if (empty($error_message)) {
                    for ($i = 0; $i < count($item_product_ids); $i++) {
                        $pid = (int)($item_product_ids[$i] ?? 0);
                        $qty = (int)($item_quantities[$i] ?? 0);
                        $price = (float)($item_prices[$i] ?? 0);

                        if ($pid <= 0) {
                            continue;
                        }

                        if ($qty <= 0) {
                            $error_message = "Quantity must be greater than zero for all selected products.";
                            break;
                        }

                        if ($price < 0) {
                            $error_message = "Unit cost cannot be negative.";
                            break;
                        }

                        // Check product exists and is active
                        $prod_chk = mysqli_prepare($conn, "SELECT id, product_name FROM products WHERE id = ? AND status = 'Active' LIMIT 1");
                        mysqli_stmt_bind_param($prod_chk, "i", $pid);
                        mysqli_stmt_execute($prod_chk);
                        $res_p = mysqli_stmt_get_result($prod_chk);
                        if (mysqli_num_rows($res_p) === 0) {
                            $error_message = "One or more selected products do not exist or are inactive.";
                            mysqli_stmt_close($prod_chk);
                            break;
                        }
                        mysqli_stmt_close($prod_chk);

                        $subtotal = round($qty * $price, 2);
                        $grand_total += $subtotal;
                        $clean_items[] = [
                            'product_id' => $pid,
                            'quantity'   => $qty,
                            'price'      => $price,
                            'subtotal'   => $subtotal
                        ];
                    }
                }

                if (empty($error_message) && empty($clean_items)) {
                    $error_message = "Please select at least one valid product with positive quantity.";
                } elseif (empty($error_message)) {
                    // Finalize payment status and amount paid
                    $amount_paid = 0.00;
                    if ($payment_status === 'Paid') {
                        $amount_paid = $grand_total;
                    } elseif ($payment_status === 'Unpaid') {
                        $amount_paid = 0.00;
                    } elseif ($payment_status === 'Partial') {
                        $amount_paid = isset($_POST['amount_paid']) ? (float)$_POST['amount_paid'] : 0.00;
                        if ($amount_paid <= 0) {
                            $payment_status = 'Unpaid';
                            $amount_paid = 0.00;
                        } elseif ($amount_paid >= $grand_total) {
                            $payment_status = 'Paid';
                            $amount_paid = $grand_total;
                        }
                    }

                    // Execute Database Transaction
                    mysqli_begin_transaction($conn);

                    try {
                        // 1. Insert into purchases header
                        $ins_p = mysqli_prepare($conn, "INSERT INTO purchases (purchase_id, supplier_id, user_id, purchase_date, total_amount, payment_status, notes) 
                                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                        mysqli_stmt_bind_param($ins_p, "siisdss", $purchase_id, $supplier_id, $purchaser_id, $purchase_date, $grand_total, $payment_status, $notes);
                        if (!mysqli_stmt_execute($ins_p)) {
                            throw new Exception("Failed to save purchase header: " . mysqli_stmt_error($ins_p));
                        }
                        $inserted_purchase_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($ins_p);

                        // 2. Insert items & Update Stock
                        $ins_item = mysqli_prepare($conn, "INSERT INTO purchase_items (purchase_id, product_id, quantity, purchase_price, subtotal) VALUES (?, ?, ?, ?, ?)");
                        
                        foreach ($clean_items as $item) {
                            // Insert purchase item
                            mysqli_stmt_bind_param($ins_item, "iiidd", $inserted_purchase_id, $item['product_id'], $item['quantity'], $item['price'], $item['subtotal']);
                            if (!mysqli_stmt_execute($ins_item)) {
                                throw new Exception("Failed to save purchase item: " . mysqli_stmt_error($ins_item));
                            }

                            // Fetch current stock with row lock
                            $stk_q = mysqli_prepare($conn, "SELECT id, quantity FROM stock WHERE user_id = ? AND product_id = ? FOR UPDATE");
                            mysqli_stmt_bind_param($stk_q, "ii", $purchaser_id, $item['product_id']);
                            mysqli_stmt_execute($stk_q);
                            $stk_res = mysqli_stmt_get_result($stk_q);
                            $existing_stock = mysqli_fetch_assoc($stk_res);
                            mysqli_stmt_close($stk_q);

                            $curr_qty = $existing_stock ? (int)$existing_stock['quantity'] : 0;
                            $new_qty = $curr_qty + $item['quantity'];

                            if ($existing_stock) {
                                $up_stk = mysqli_prepare($conn, "UPDATE stock SET quantity = ? WHERE id = ?");
                                mysqli_stmt_bind_param($up_stk, "ii", $new_qty, $existing_stock['id']);
                                if (!mysqli_stmt_execute($up_stk)) {
                                    throw new Exception("Failed to update stock quantity: " . mysqli_stmt_error($up_stk));
                                }
                                mysqli_stmt_close($up_stk);
                            } else {
                                $ins_stk = mysqli_prepare($conn, "INSERT INTO stock (user_id, product_id, quantity) VALUES (?, ?, ?)");
                                mysqli_stmt_bind_param($ins_stk, "iii", $purchaser_id, $item['product_id'], $new_qty);
                                if (!mysqli_stmt_execute($ins_stk)) {
                                    throw new Exception("Failed to initialize stock record: " . mysqli_stmt_error($ins_stk));
                                }
                                mysqli_stmt_close($ins_stk);
                            }

                            // 3. Log stock movement audit trail
                            $mov_note = "Purchase received: {$purchase_id}";
                            $mov = mysqli_prepare($conn, "INSERT INTO stock_movements (user_id, product_id, movement_type, quantity, balance_before, balance_after, reference_id, notes) 
                                                         VALUES (?, ?, 'Purchase', ?, ?, ?, ?, ?)");
                            // Exactly 7 placeholders and 7 bind variables: user_id(i), product_id(i), quantity(i), balance_before(i), balance_after(i), reference_id(s), notes(s)
                            mysqli_stmt_bind_param($mov, "iiiiiss", $purchaser_id, $item['product_id'], $item['quantity'], $curr_qty, $new_qty, $purchase_id, $mov_note);
                            if (!mysqli_stmt_execute($mov)) {
                                throw new Exception("Failed to record stock movement: " . mysqli_stmt_error($mov));
                            }
                            mysqli_stmt_close($mov);
                        }
                        mysqli_stmt_close($ins_item);

                        // 4. Record payment if Paid or Partial
                        if ($amount_paid > 0) {
                            $pay_id = 'PAY-' . $purchase_id;
                            $pay_note = ($payment_status === 'Paid') ? 'Full Purchase Settlement' : 'Partial Purchase Settlement';
                            $pay_stmt = mysqli_prepare($conn, "INSERT INTO payments (payment_id, transaction_type, reference_id, user_id, amount, payment_date, payment_method, notes) 
                                                               VALUES (?, 'Purchase', ?, ?, ?, ?, ?, ?)");
                            // 7 placeholders and 7 bind variables: payment_id(s), reference_id(s), user_id(i), amount(d), payment_date(s), payment_method(s), notes(s)
                            mysqli_stmt_bind_param($pay_stmt, "ssidsss", $pay_id, $purchase_id, $purchaser_id, $amount_paid, $purchase_date, $payment_method, $pay_note);
                            if (!mysqli_stmt_execute($pay_stmt)) {
                                throw new Exception("Failed to record payment transaction: " . mysqli_stmt_error($pay_stmt));
                            }
                            mysqli_stmt_close($pay_stmt);
                        }

                        // Commit transaction
                        mysqli_commit($conn);

                        set_flash('success', "Purchase Order {$purchase_id} recorded successfully! Inventory has been credited.");
                        header("Location: view.php?id=" . $inserted_purchase_id);
                        exit;

                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error_message = "Purchase transaction failed: " . $e->getMessage();
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
        <h1>Create Supplier Purchase</h1>
        <div class="page-breadcrumb">Smart Business / Purchases / New Entry</div>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Purchases</a>
    </div>
</div>

<div class="page-content">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo e($error_message); ?>
        </div>
    <?php endif; ?>

    <form action="create.php" method="POST" id="purchaseForm" autocomplete="off">
        <?php echo csrf_input(); ?>

        <!-- Purchase Header Card -->
        <div class="card">
            <div class="card-header">
                <span>Purchase Information</span>
                <span class="badge badge-info">Inventory Inward</span>
            </div>
            <div class="card-body">
                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="purchase_id">Purchase / PO Number <span style="color:#c9302c;">*</span></label>
                            <input type="text" id="purchase_id" name="purchase_id" class="form-control" 
                                   value="<?php echo isset($_POST['purchase_id']) ? e($_POST['purchase_id']) : e($suggested_purchase_id); ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="purchase_date">Purchase Date <span style="color:#c9302c;">*</span></label>
                            <input type="date" id="purchase_date" name="purchase_date" class="form-control" 
                                   value="<?php echo isset($_POST['purchase_date']) ? e($_POST['purchase_date']) : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="supplier_id">Select Supplier <span style="color:#c9302c;">*</span></label>
                            <select id="supplier_id" name="supplier_id" class="form-control" required>
                                <option value="">-- Choose Supplier --</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?php echo e($sup['id']); ?>" <?php echo ((string)($_POST['supplier_id'] ?? '') === (string)$sup['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($sup['supplier_name']); ?> <?php echo !empty($sup['company_name']) ? '(' . e($sup['company_name']) . ')' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="grid-row" style="margin-bottom: 0;">
                            <div class="grid-col-6">
                                <div class="form-group">
                                    <label for="payment_status">Payment Status</label>
                                    <select id="payment_status" name="payment_status" class="form-control" onchange="handlePaymentStatusChange(this.value)">
                                        <option value="Paid" <?php echo (($_POST['payment_status'] ?? '') === 'Paid' || !isset($_POST['payment_status'])) ? 'selected' : ''; ?>>Paid</option>
                                        <option value="Partial" <?php echo (($_POST['payment_status'] ?? '') === 'Partial') ? 'selected' : ''; ?>>Partial</option>
                                        <option value="Unpaid" <?php echo (($_POST['payment_status'] ?? '') === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid-col-6">
                                <div class="form-group">
                                    <label for="payment_method">Payment Mode</label>
                                    <select id="payment_method" name="payment_method" class="form-control">
                                        <option value="Bank Transfer" <?php echo (($_POST['payment_method'] ?? '') === 'Bank Transfer' || !isset($_POST['payment_method'])) ? 'selected' : ''; ?>>Bank Transfer</option>
                                        <option value="Cash" <?php echo (($_POST['payment_method'] ?? '') === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                                        <option value="Card" <?php echo (($_POST['payment_method'] ?? '') === 'Card') ? 'selected' : ''; ?>>Card</option>
                                        <option value="UPI" <?php echo (($_POST['payment_method'] ?? '') === 'UPI') ? 'selected' : ''; ?>>UPI</option>
                                        <option value="Credit" <?php echo (($_POST['payment_method'] ?? '') === 'Credit') ? 'selected' : ''; ?>>Credit</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div id="partialAmountRow" style="margin-top: 10px; display: <?php echo (($_POST['payment_status'] ?? '') === 'Partial') ? 'block' : 'none'; ?>;">
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="amount_paid">Partial Amount Paid (₹) <span style="color:#c9302c;">*</span></label>
                                <input type="number" step="0.01" min="0" id="amount_paid" name="amount_paid" class="form-control" placeholder="0.00" value="<?php echo isset($_POST['amount_paid']) ? e($_POST['amount_paid']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (is_admin()): ?>
                    <div class="form-group" style="margin-top: 16px; margin-bottom: 0;">
                        <label for="stockiest_id">Receiving Stockiest Warehouse <span style="color:#c9302c;">*</span></label>
                        <select id="stockiest_id" name="stockiest_id" class="form-control" required>
                            <?php foreach ($stockiests as $stk): ?>
                                <option value="<?php echo e($stk['id']); ?>" <?php echo ((string)($_POST['stockiest_id'] ?? '') === (string)$stk['id']) ? 'selected' : ''; ?>>
                                    <?php echo e($stk['full_name']); ?> (<?php echo e($stk['user_id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #64748b; font-size: 11.5px; display: block; margin-top: 4px;">
                            As Administrator, select which Central Stockiest warehouse receives and holds this physical inventory.
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Product Line Items Card -->
        <div class="card">
            <div class="card-header">
                <span>Purchased Products</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addItemRow()">+ Add Another Product</button>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table" id="itemsTable" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Product</th>
                            <th style="width: 20%; text-align: right;">Unit Cost (₹)</th>
                            <th style="width: 15%; text-align: right;">Quantity</th>
                            <th style="width: 20%; text-align: right;">Subtotal (₹)</th>
                            <th style="width: 5%; text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody">
                        <!-- Initial Row -->
                        <tr class="item-row">
                            <td>
                                <select name="product_ids[]" class="form-control product-select" required onchange="handleProductChange(this)">
                                    <option value="">-- Select Product --</option>
                                    <?php foreach ($products as $p): ?>
                                        <option value="<?php echo e($p['id']); ?>" data-price="<?php echo e($p['purchase_price']); ?>" data-unit="<?php echo e($p['unit']); ?>">
                                            [<?php echo e($p['product_id']); ?>] <?php echo e($p['product_name']); ?> (<?php echo e($p['unit']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="prices[]" class="form-control item-price" placeholder="0.00" style="text-align: right;" required oninput="calculateTotals()">
                            </td>
                            <td>
                                <input type="number" min="1" name="quantities[]" class="form-control item-qty" value="1" placeholder="Qty" style="text-align: right;" required oninput="calculateTotals()">
                            </td>
                            <td style="text-align: right; vertical-align: middle;">
                                <strong class="item-subtotal">₹0.00</strong>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeItemRow(this)">&times;</button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background: #fafbfc; font-size: 14px;">
                            <th colspan="3" style="text-align: right;">Grand Total:</th>
                            <th style="text-align: right; font-size: 16px; color: #1a365d;">
                                <span id="grandTotalDisplay">₹0.00</span>
                            </th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Notes Card -->
        <div class="card">
            <div class="card-body">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="notes">Purchase Notes / Remarks</label>
                    <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="e.g. Invoice # from supplier, batch details, shipping notes..."></textarea>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 30px; display: flex; gap: 10px;">
            <button type="submit" id="submitBtn" class="btn btn-primary" style="padding: 10px 24px; font-size: 14px;">&check; Confirm &amp; Save Purchase</button>
            <a href="index.php" class="btn btn-secondary" style="padding: 10px 20px;">Cancel</a>
        </div>
    </form>
</div>

<script>
const productsCatalog = <?php echo json_encode($products); ?>;

function handlePaymentStatusChange(status) {
    const row = document.getElementById('partialAmountRow');
    if (row) {
        row.style.display = (status === 'Partial') ? 'block' : 'none';
        const input = document.getElementById('amount_paid');
        if (status === 'Partial' && input) {
            input.focus();
        }
    }
}

function handleProductChange(selectElem) {
    const row = selectElem.closest('.item-row');
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    const price = selectedOption.getAttribute('data-price') || 0;
    const priceInput = row.querySelector('.item-price');
    priceInput.value = parseFloat(price).toFixed(2);
    calculateTotals();
}

function addItemRow() {
    const tbody = document.getElementById('itemsBody');
    const firstRow = document.querySelector('.item-row');
    const newRow = firstRow.cloneNode(true);
    
    // Clear values in cloned row
    newRow.querySelector('.product-select').selectedIndex = 0;
    newRow.querySelector('.item-price').value = '';
    newRow.querySelector('.item-qty').value = '1';
    newRow.querySelector('.item-subtotal').textContent = '₹0.00';

    tbody.appendChild(newRow);
}

function removeItemRow(btn) {
    const rows = document.querySelectorAll('.item-row');
    if (rows.length > 1) {
        btn.closest('.item-row').remove();
        calculateTotals();
    } else {
        alert("At least one product item is required.");
    }
}

function calculateTotals() {
    let grand = 0;
    const rows = document.querySelectorAll('.item-row');
    rows.forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const sub = qty * price;
        row.querySelector('.item-subtotal').textContent = '₹' + sub.toFixed(2);
        grand += sub;
    });
    document.getElementById('grandTotalDisplay').textContent = '₹' + grand.toFixed(2);
}

// Prevent double submissions
let isPurchaseSubmitting = false;
document.getElementById('purchaseForm').addEventListener('submit', function(e) {
    if (isPurchaseSubmitting) {
        e.preventDefault();
        return false;
    }
    isPurchaseSubmitting = true;
    const btn = document.getElementById('submitBtn');
    if (btn) {
        btn.innerHTML = '&bull; Saving Purchase...';
        btn.style.opacity = '0.75';
        btn.style.pointerEvents = 'none';
    }
});
</script>

<?php include '../includes/footer.php'; ?>
