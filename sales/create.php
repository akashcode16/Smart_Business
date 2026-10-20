<?php
/**
 * Sales Management - Create POS Sales Invoice
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'POS Sales Billing';
$active_menu = 'sales';

$error_message = '';
$seller_id = (int)$current_user['id'];

// Generate Invoice Number (e.g. INV001)
$inv_res = mysqli_query($conn, "SELECT sale_id FROM sales WHERE sale_id LIKE 'INV%' ORDER BY id DESC LIMIT 1");
$next_inv = 1;
if ($inv_res && $row = mysqli_fetch_assoc($inv_res)) {
    $next_inv = (int)substr($row['sale_id'], 3) + 1;
}
$suggested_invoice_id = sprintf("INV%03d", $next_inv);

// Fetch customers: either created by this user or general
$cust_res = mysqli_query($conn, "SELECT id, customer_id, customer_name, phone FROM customers WHERE status = 'Active' ORDER BY customer_name ASC");
$customers = [];
while ($c = mysqli_fetch_assoc($cust_res)) {
    $customers[] = $c;
}

// Fetch products available in seller's inventory
$stk_res = mysqli_query($conn, "SELECT s.product_id, s.quantity as available_qty, 
                                p.product_id as sku, p.product_name, p.selling_price, p.unit 
                                FROM stock s 
                                JOIN products p ON s.product_id = p.id 
                                WHERE s.user_id = {$seller_id} AND s.quantity > 0 
                                ORDER BY p.product_name ASC");
$available_products = [];
while ($p = mysqli_fetch_assoc($stk_res)) {
    $available_products[] = $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $sale_id = strtoupper(trim($_POST['sale_id'] ?? ''));
        $customer_id_raw = $_POST['customer_id'] ?? '';
        $customer_id = (!empty($customer_id_raw) && $customer_id_raw !== 'walkin') ? (int)$customer_id_raw : null;
        $sale_date = trim($_POST['sale_date'] ?? date('Y-m-d'));
        $payment_method = in_array($_POST['payment_method'] ?? '', ['Cash', 'Card', 'UPI', 'Bank Transfer', 'Credit']) ? $_POST['payment_method'] : 'Cash';
        $payment_status = in_array($_POST['payment_status'] ?? '', ['Paid', 'Partial', 'Unpaid']) ? $_POST['payment_status'] : 'Paid';
        $amount_paid = (float)($_POST['amount_paid'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');

        $item_product_ids = $_POST['product_ids'] ?? [];
        $item_quantities = $_POST['quantities'] ?? [];
        $item_prices = $_POST['prices'] ?? [];

        if (empty($sale_id) || empty($sale_date)) {
            $error_message = "Invoice ID and Date are required.";
        } elseif (empty($item_product_ids) || !is_array($item_product_ids)) {
            $error_message = "Please add at least one product item to the invoice.";
        } else {
            // Check unique sale_id
            $chk = mysqli_prepare($conn, "SELECT id FROM sales WHERE sale_id = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, "s", $sale_id);
            mysqli_stmt_execute($chk);
            $res = mysqli_stmt_get_result($chk);

            if (mysqli_num_rows($res) > 0) {
                $error_message = "Invoice # '{$sale_id}' already exists.";
                mysqli_stmt_close($chk);
            } else {
                mysqli_stmt_close($chk);

                // Clean items and calculate total
                $clean_items = [];
                $grand_total = 0.00;

                for ($i = 0; $i < count($item_product_ids); $i++) {
                    $pid = (int)($item_product_ids[$i] ?? 0);
                    $qty = (int)($item_quantities[$i] ?? 0);
                    $price = (float)($item_prices[$i] ?? 0);

                    if ($pid > 0 && $qty > 0 && $price >= 0) {
                        $subtotal = $qty * $price;
                        $grand_total += $subtotal;
                        $clean_items[] = [
                            'product_id' => $pid,
                            'quantity'   => $qty,
                            'price'      => $price,
                            'subtotal'   => $subtotal
                        ];
                    }
                }

                if (empty($clean_items)) {
                    $error_message = "Please specify valid items with quantities > 0.";
                } else {
                    // Check amount paid bounds
                    if ($payment_status === 'Paid') {
                        $amount_paid = $grand_total;
                    } elseif ($payment_status === 'Unpaid') {
                        $amount_paid = 0.00;
                    } else { // Partial
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
                        // 1. Insert into sales table
                        if ($customer_id === null) {
                            $ins_sale = mysqli_prepare($conn, "INSERT INTO sales (sale_id, customer_id, user_id, sale_date, total_amount, payment_method, payment_status, notes) 
                                                               VALUES (?, NULL, ?, ?, ?, ?, ?, ?)");
                            mysqli_stmt_bind_param($ins_sale, "sisdsss", $sale_id, $seller_id, $sale_date, $grand_total, $payment_method, $payment_status, $notes);
                        } else {
                            $ins_sale = mysqli_prepare($conn, "INSERT INTO sales (sale_id, customer_id, user_id, sale_date, total_amount, payment_method, payment_status, notes) 
                                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            mysqli_stmt_bind_param($ins_sale, "siisdsss", $sale_id, $customer_id, $seller_id, $sale_date, $grand_total, $payment_method, $payment_status, $notes);
                        }
                        mysqli_stmt_execute($ins_sale);
                        $inserted_sale_id = mysqli_insert_id($conn);
                        mysqli_stmt_close($ins_sale);

                        // 2. Insert items and reduce stock atomically
                        $ins_item = mysqli_prepare($conn, "INSERT INTO sale_items (sale_id, product_id, quantity, selling_price, subtotal) VALUES (?, ?, ?, ?, ?)");

                        foreach ($clean_items as $item) {
                            // Insert sale item
                            mysqli_stmt_bind_param($ins_item, "iiidd", $inserted_sale_id, $item['product_id'], $item['quantity'], $item['price'], $item['subtotal']);
                            mysqli_stmt_execute($ins_item);

                            // Lock stock row
                            $stk_lock = mysqli_prepare($conn, "SELECT id, quantity FROM stock WHERE user_id = ? AND product_id = ? FOR UPDATE");
                            mysqli_stmt_bind_param($stk_lock, "ii", $seller_id, $item['product_id']);
                            mysqli_stmt_execute($stk_lock);
                            $stk_res = mysqli_stmt_get_result($stk_lock);
                            $stock_row = mysqli_fetch_assoc($stk_res);
                            mysqli_stmt_close($stk_lock);

                            $curr_stock = $stock_row ? (int)$stock_row['quantity'] : 0;
                            if ($curr_stock < $item['quantity']) {
                                throw new Exception("Insufficient stock! Available: {$curr_stock}, requested: {$item['quantity']}.");
                            }

                            $new_stock = $curr_stock - $item['quantity'];

                            // Update stock
                            $up_stk = mysqli_prepare($conn, "UPDATE stock SET quantity = ? WHERE id = ?");
                            mysqli_stmt_bind_param($up_stk, "ii", $new_stock, $stock_row['id']);
                            mysqli_stmt_execute($up_stk);
                            mysqli_stmt_close($up_stk);

                            // Audit movement
                            $neg_qty = -$item['quantity'];
                            $mov_note = "Sale invoice: {$sale_id}";
                            $mov = mysqli_prepare($conn, "INSERT INTO stock_movements (user_id, product_id, movement_type, quantity, balance_before, balance_after, reference_id, notes) 
                                                         VALUES (?, ?, 'Sale', ?, ?, ?, ?, ?)");
                            mysqli_stmt_bind_param($mov, "iiiiiss", $seller_id, $item['product_id'], $neg_qty, $curr_stock, $new_stock, $sale_id, $mov_note);
                            mysqli_stmt_execute($mov);
                            mysqli_stmt_close($mov);
                        }
                        mysqli_stmt_close($ins_item);

                        // 3. Record payment if amount_paid > 0
                        if ($amount_paid > 0) {
                            $pay_id = 'PAY-' . $sale_id;
                            $pay_stmt = mysqli_prepare($conn, "INSERT INTO payments (payment_id, transaction_type, reference_id, user_id, amount, payment_date, payment_method, notes) 
                                                               VALUES (?, 'Sale', ?, ?, ?, ?, ?, 'Initial Sales Payment')");
                            mysqli_stmt_bind_param($pay_stmt, "ssisss", $pay_id, $sale_id, $seller_id, $amount_paid, $sale_date, $payment_method);
                            mysqli_stmt_execute($pay_stmt);
                            mysqli_stmt_close($pay_stmt);
                        }

                        // Commit transaction
                        mysqli_commit($conn);

                        set_flash('success', "Invoice {$sale_id} created successfully! Inventory deducted.");
                        header("Location: view.php?id=" . $inserted_sale_id);
                        exit;

                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        $error_message = "Transaction error: " . $e->getMessage();
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
        <h1>New POS Sales Invoice</h1>
        <div class="page-breadcrumb">Smart Business / Sales / POS Billing</div>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Sales</a>
    </div>
</div>

<div class="page-content">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo e($error_message); ?>
        </div>
    <?php endif; ?>

    <?php if (empty($available_products)): ?>
        <div class="alert alert-warning">
            <strong>Notice:</strong> Your business account currently has zero items in available inventory. 
            <?php if (is_retailer()): ?>
                Please request a stock transfer from your Distributor before making sales.
            <?php else: ?>
                Please inward stock or adjust inventory before generating sales invoices.
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form action="create.php" method="POST" id="salesForm" autocomplete="off">
        <?php echo csrf_input(); ?>

        <!-- Invoice Details Header -->
        <div class="card">
            <div class="card-header">
                <span>Invoice Information</span>
                <span class="badge badge-info">Counter POS Terminal</span>
            </div>
            <div class="card-body">
                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="sale_id">Invoice Number <span style="color:#c9302c;">*</span></label>
                            <input type="text" id="sale_id" name="sale_id" class="form-control" 
                                   value="<?php echo isset($_POST['sale_id']) ? e($_POST['sale_id']) : e($suggested_invoice_id); ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="sale_date">Invoice Date <span style="color:#c9302c;">*</span></label>
                            <input type="date" id="sale_date" name="sale_date" class="form-control" 
                                   value="<?php echo isset($_POST['sale_date']) ? e($_POST['sale_date']) : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="customer_id">Select Customer</label>
                            <select id="customer_id" name="customer_id" class="form-control">
                                <option value="walkin">-- Walk-in / Direct Retail Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                    <option value="<?php echo e($c['id']); ?>" <?php echo ((string)($_POST['customer_id'] ?? '') === (string)$c['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($c['customer_name']); ?> (Phone: <?php echo e($c['phone']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="grid-row" style="margin-bottom: 0;">
                            <div class="grid-col-6">
                                <div class="form-group">
                                    <label for="payment_method">Payment Method</label>
                                    <select id="payment_method" name="payment_method" class="form-control">
                                        <option value="Cash" selected>Cash</option>
                                        <option value="Card">Card / POS</option>
                                        <option value="UPI">UPI / QR Code</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                        <option value="Credit">Credit Ledger</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid-col-6">
                                <div class="form-group">
                                    <label for="payment_status">Payment Status</label>
                                    <select id="payment_status" name="payment_status" class="form-control" onchange="handlePaymentStatusChange(this.value)">
                                        <option value="Paid" selected>Paid in Full</option>
                                        <option value="Partial">Partial Payment</option>
                                        <option value="Unpaid">Unpaid (Credit)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="partialPayContainer" style="display: none; margin-top: 10px;">
                    <div class="form-group" style="max-width: 300px;">
                        <label for="amount_paid">Amount Paid Now (₹)</label>
                        <input type="number" step="0.01" min="0" id="amount_paid" name="amount_paid" class="form-control" placeholder="0.00" value="0.00">
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Line Items -->
        <div class="card">
            <div class="card-header">
                <span>Invoice Items</span>
                <button type="button" class="btn btn-secondary btn-sm" onclick="addSaleRow()">+ Add Product</button>
            </div>
            <div class="card-body" style="padding: 0;">
                <table class="data-table" id="salesItemsTable" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 45%;">Product Description</th>
                            <th style="width: 18%; text-align: right;">Unit Price (₹)</th>
                            <th style="width: 15%; text-align: right;">Quantity</th>
                            <th style="width: 17%; text-align: right;">Line Total (₹)</th>
                            <th style="width: 5%; text-align: center;"></th>
                        </tr>
                    </thead>
                    <tbody id="salesItemsBody">
                        <tr class="sale-item-row">
                            <td>
                                <select name="product_ids[]" class="form-control sale-prod-select" required onchange="handleSaleProdChange(this)">
                                    <option value="">-- Choose Product --</option>
                                    <?php foreach ($available_products as $p): ?>
                                        <option value="<?php echo e($p['product_id']); ?>" 
                                                data-price="<?php echo e($p['selling_price']); ?>" 
                                                data-avail="<?php echo e($p['available_qty']); ?>"
                                                data-unit="<?php echo e($p['unit']); ?>">
                                            [<?php echo e($p['sku']); ?>] <?php echo e($p['product_name']); ?> (In Stock: <?php echo number_format($p['available_qty']); ?> <?php echo e($p['unit']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="stock-note" style="color: #2b5797; font-size: 11px;"></small>
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" name="prices[]" class="form-control item-price" placeholder="0.00" style="text-align: right;" required oninput="calcSalesTotals()">
                            </td>
                            <td>
                                <input type="number" min="1" name="quantities[]" class="form-control item-qty" value="1" placeholder="Qty" style="text-align: right;" required oninput="calcSalesTotals()">
                            </td>
                            <td style="text-align: right; vertical-align: middle;">
                                <strong class="item-subtotal">₹0.00</strong>
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeSaleRow(this)">&times;</button>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr style="background: #fafbfc; font-size: 15px;">
                            <th colspan="3" style="text-align: right;">Invoice Grand Total:</th>
                            <th style="text-align: right; color: #1a365d; font-size: 18px;">
                                <span id="salesGrandTotal">₹0.00</span>
                            </th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="notes">Invoice Comments / Payment Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="e.g. UPI Transaction ID, Cheque number, delivery instructions..."></textarea>
                </div>
            </div>
        </div>

        <div style="margin-bottom: 30px; display: flex; gap: 10px;">
            <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-size: 14px;" <?php echo empty($available_products) ? 'disabled' : ''; ?>>
                &check; Generate &amp; Print Invoice
            </button>
            <a href="index.php" class="btn btn-secondary" style="padding: 10px 20px;">Cancel</a>
        </div>
    </form>
</div>

<script>
function handleSaleProdChange(selectElem) {
    const row = selectElem.closest('.sale-item-row');
    const opt = selectElem.options[selectElem.selectedIndex];
    const price = opt.getAttribute('data-price') || 0;
    const avail = opt.getAttribute('data-avail') || 0;
    const unit = opt.getAttribute('data-unit') || '';

    const priceInput = row.querySelector('.item-price');
    const qtyInput = row.querySelector('.item-qty');
    const note = row.querySelector('.stock-note');

    priceInput.value = parseFloat(price).toFixed(2);
    if (avail) {
        qtyInput.max = avail;
        note.textContent = 'Available in stock: ' + avail + ' ' + unit;
    } else {
        qtyInput.removeAttribute('max');
        note.textContent = '';
    }
    calcSalesTotals();
}

function addSaleRow() {
    const tbody = document.getElementById('salesItemsBody');
    const firstRow = document.querySelector('.sale-item-row');
    const newRow = firstRow.cloneNode(true);

    newRow.querySelector('.sale-prod-select').selectedIndex = 0;
    newRow.querySelector('.item-price').value = '';
    newRow.querySelector('.item-qty').value = '1';
    newRow.querySelector('.item-qty').removeAttribute('max');
    newRow.querySelector('.stock-note').textContent = '';
    newRow.querySelector('.item-subtotal').textContent = '₹0.00';

    tbody.appendChild(newRow);
}

function removeSaleRow(btn) {
    const rows = document.querySelectorAll('.sale-item-row');
    if (rows.length > 1) {
        btn.closest('.sale-item-row').remove();
        calcSalesTotals();
    } else {
        alert("At least one product item is required for the invoice.");
    }
}

function calcSalesTotals() {
    let grand = 0;
    const rows = document.querySelectorAll('.sale-item-row');
    rows.forEach(row => {
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const sub = qty * price;
        row.querySelector('.item-subtotal').textContent = '₹' + sub.toFixed(2);
        grand += sub;
    });
    document.getElementById('salesGrandTotal').textContent = '₹' + grand.toFixed(2);
}

function handlePaymentStatusChange(val) {
    const container = document.getElementById('partialPayContainer');
    if (val === 'Partial') {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>
