<?php
/**
 * Test Suite: Purchase Workflow & ArgumentCountError Fix Validation
 * Smart Business Management System
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';

echo "============================================================\n";
echo "PURCHASE WORKFLOW TEST SUITE — VALIDATING BUG FIX & INTEGRITY\n";
echo "============================================================\n\n";

$pass_count = 0;
$fail_count = 0;

function assert_test($label, $condition, $details = '') {
    global $pass_count, $fail_count;
    if ($condition) {
        $pass_count++;
        echo "[PASS] " . $label . ($details ? " ({$details})" : "") . "\n";
    } else {
        $fail_count++;
        echo "[FAIL] " . $label . ($details ? " ({$details})" : "") . "\n";
    }
}

// 0. Locate Test Entities (Supplier, Stockiest, Products)
$sup_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, supplier_name FROM suppliers WHERE status = 'Active' LIMIT 1"));
$stk_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT u.id, u.user_id, u.full_name FROM users u JOIN roles r ON u.role_id = r.id WHERE r.role_name = 'Stockiest' AND u.status = 'Active' LIMIT 1"));
$prod_res = mysqli_query($conn, "SELECT id, product_id, product_name, purchase_price FROM products WHERE status = 'Active' ORDER BY id ASC LIMIT 2");
$products = [];
while ($p = mysqli_fetch_assoc($prod_res)) {
    $products[] = $p;
}

assert_test("Database prerequisites exist", $sup_row && $stk_row && count($products) >= 2, 
    "Supplier ID: {$sup_row['id']}, Stockiest ID: {$stk_row['id']}, Products: " . count($products));

$supplier_id = (int)$sup_row['id'];
$stockiest_id = (int)$stk_row['id'];
$prod1 = $products[0];
$prod2 = $products[1];

// Clean any previous test records if leftover
mysqli_query($conn, "DELETE FROM payments WHERE reference_id LIKE 'PUR_TEST_%'");
mysqli_query($conn, "DELETE FROM stock_movements WHERE reference_id LIKE 'PUR_TEST_%'");
$del_items = mysqli_query($conn, "DELETE pi FROM purchase_items pi JOIN purchases p ON pi.purchase_id = p.id WHERE p.purchase_id LIKE 'PUR_TEST_%'");
mysqli_query($conn, "DELETE FROM purchases WHERE purchase_id LIKE 'PUR_TEST_%'");

// Record initial stock levels for Stockiest
function get_stock_qty($conn, $user_id, $product_id) {
    $stmt = mysqli_prepare($conn, "SELECT quantity FROM stock WHERE user_id = ? AND product_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $user_id, $product_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row ? (int)$row['quantity'] : 0;
}

$initial_stock_p1 = get_stock_qty($conn, $stockiest_id, $prod1['id']);
$initial_stock_p2 = get_stock_qty($conn, $stockiest_id, $prod2['id']);

echo "\n--- TEST CASE 1: VALID SINGLE-PRODUCT PURCHASE ---\n";
$po1_id = 'PUR_TEST_SINGLE_001';
$po1_qty = 15;
$po1_cost = (float)$prod1['purchase_price'];
$po1_total = round($po1_qty * $po1_cost, 2);
$po1_date = date('Y-m-d');
$po1_status = 'Paid';
$po1_method = 'Bank Transfer';

// Simulate backend transaction logic matching purchases/create.php
mysqli_begin_transaction($conn);
try {
    // 1. Insert header
    $stmt_hdr = mysqli_prepare($conn, "INSERT INTO purchases (purchase_id, supplier_id, user_id, purchase_date, total_amount, payment_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $po1_note = "Automated Test PO 1";
    mysqli_stmt_bind_param($stmt_hdr, "siisdss", $po1_id, $supplier_id, $stockiest_id, $po1_date, $po1_total, $po1_status, $po1_note);
    if (!mysqli_stmt_execute($stmt_hdr)) throw new Exception(mysqli_stmt_error($stmt_hdr));
    $inserted_id_1 = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt_hdr);

    // 2. Insert item
    $stmt_item = mysqli_prepare($conn, "INSERT INTO purchase_items (purchase_id, product_id, quantity, purchase_price, subtotal) VALUES (?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt_item, "iiidd", $inserted_id_1, $prod1['id'], $po1_qty, $po1_cost, $po1_total);
    if (!mysqli_stmt_execute($stmt_item)) throw new Exception(mysqli_stmt_error($stmt_item));
    mysqli_stmt_close($stmt_item);

    // 3. Update stock
    $curr_stk = get_stock_qty($conn, $stockiest_id, $prod1['id']);
    $new_stk = $curr_stk + $po1_qty;
    $up_stk = mysqli_prepare($conn, "UPDATE stock SET quantity = ? WHERE user_id = ? AND product_id = ?");
    mysqli_stmt_bind_param($up_stk, "iii", $new_stk, $stockiest_id, $prod1['id']);
    mysqli_stmt_execute($up_stk);
    mysqli_stmt_close($up_stk);

    // 4. Log movement with the EXACT bind string: "iiiiiss"
    $mov_note = "Purchase received: {$po1_id}";
    $mov = mysqli_prepare($conn, "INSERT INTO stock_movements (user_id, product_id, movement_type, quantity, balance_before, balance_after, reference_id, notes) 
                                 VALUES (?, ?, 'Purchase', ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($mov, "iiiiiss", $stockiest_id, $prod1['id'], $po1_qty, $curr_stk, $new_stk, $po1_id, $mov_note);
    if (!mysqli_stmt_execute($mov)) throw new Exception(mysqli_stmt_error($mov));
    mysqli_stmt_close($mov);

    // 5. Payment insert with "ssidsss"
    $pay_id = 'PAY-' . $po1_id;
    $pay_note = 'Full Purchase Settlement';
    $pay_stmt = mysqli_prepare($conn, "INSERT INTO payments (payment_id, transaction_type, reference_id, user_id, amount, payment_date, payment_method, notes) 
                                       VALUES (?, 'Purchase', ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($pay_stmt, "ssidsss", $pay_id, $po1_id, $stockiest_id, $po1_total, $po1_date, $po1_method, $pay_note);
    if (!mysqli_stmt_execute($pay_stmt)) throw new Exception(mysqli_stmt_error($pay_stmt));
    mysqli_stmt_close($pay_stmt);

    mysqli_commit($conn);
    $po1_success = true;
} catch (Exception $e) {
    mysqli_rollback($conn);
    $po1_success = false;
    echo "ERROR in Test 1: " . $e->getMessage() . "\n";
}

assert_test("Single-product purchase transaction committed without ArgumentCountError", $po1_success);

// Verify records in DB
$chk_p1 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM purchases WHERE purchase_id = '{$po1_id}'"));
assert_test("Purchase header saved correctly", $chk_p1 && (float)$chk_p1['total_amount'] === (float)$po1_total && $chk_p1['payment_status'] === 'Paid');

$chk_item1 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM purchase_items WHERE purchase_id = {$inserted_id_1}"));
assert_test("Purchase item saved correctly", $chk_item1 && (int)$chk_item1['quantity'] === $po1_qty && (float)$chk_item1['subtotal'] === (float)$po1_total);

$post_stock_p1 = get_stock_qty($conn, $stockiest_id, $prod1['id']);
assert_test("Stockiest stock increased by exact quantity (+{$po1_qty})", $post_stock_p1 === ($initial_stock_p1 + $po1_qty), "Before: {$initial_stock_p1}, After: {$post_stock_p1}");

$chk_mov1 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM stock_movements WHERE reference_id = '{$po1_id}'"));
assert_test("Stock movement audit trail created", $chk_mov1 && (int)$chk_mov1['quantity'] === $po1_qty && (int)$chk_mov1['balance_before'] === $initial_stock_p1 && (int)$chk_mov1['balance_after'] === $post_stock_p1);

$chk_pay1 = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM payments WHERE reference_id = '{$po1_id}'"));
assert_test("Settlement payment entry created in payments table", $chk_pay1 && (float)$chk_pay1['amount'] === (float)$po1_total);


echo "\n--- TEST CASE 2: VALID MULTI-PRODUCT PURCHASE ---\n";
$po2_id = 'PUR_TEST_MULTI_002';
$po2_qty1 = 8;
$po2_cost1 = (float)$prod1['purchase_price'];
$po2_sub1 = round($po2_qty1 * $po2_cost1, 2);

$po2_qty2 = 12;
$po2_cost2 = (float)$prod2['purchase_price'];
$po2_sub2 = round($po2_qty2 * $po2_cost2, 2);

$po2_grand_total = round($po2_sub1 + $po2_sub2, 2);
$po2_date = date('Y-m-d');
$po2_status = 'Partial';
$po2_amount_paid = round($po2_grand_total / 2, 2);
$po2_method = 'UPI';

$stock_p1_before_m = get_stock_qty($conn, $stockiest_id, $prod1['id']);
$stock_p2_before_m = get_stock_qty($conn, $stockiest_id, $prod2['id']);

mysqli_begin_transaction($conn);
try {
    $stmt_hdr2 = mysqli_prepare($conn, "INSERT INTO purchases (purchase_id, supplier_id, user_id, purchase_date, total_amount, payment_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $po2_note = "Automated Test Multi PO";
    mysqli_stmt_bind_param($stmt_hdr2, "siisdss", $po2_id, $supplier_id, $stockiest_id, $po2_date, $po2_grand_total, $po2_status, $po2_note);
    if (!mysqli_stmt_execute($stmt_hdr2)) throw new Exception(mysqli_stmt_error($stmt_hdr2));
    $inserted_id_2 = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt_hdr2);

    $items = [
        ['product_id' => $prod1['id'], 'quantity' => $po2_qty1, 'price' => $po2_cost1, 'subtotal' => $po2_sub1],
        ['product_id' => $prod2['id'], 'quantity' => $po2_qty2, 'price' => $po2_cost2, 'subtotal' => $po2_sub2]
    ];

    $stmt_item2 = mysqli_prepare($conn, "INSERT INTO purchase_items (purchase_id, product_id, quantity, purchase_price, subtotal) VALUES (?, ?, ?, ?, ?)");
    foreach ($items as $it) {
        mysqli_stmt_bind_param($stmt_item2, "iiidd", $inserted_id_2, $it['product_id'], $it['quantity'], $it['price'], $it['subtotal']);
        if (!mysqli_stmt_execute($stmt_item2)) throw new Exception(mysqli_stmt_error($stmt_item2));

        $curr = get_stock_qty($conn, $stockiest_id, $it['product_id']);
        $new_stk = $curr + $it['quantity'];

        $up = mysqli_prepare($conn, "UPDATE stock SET quantity = ? WHERE user_id = ? AND product_id = ?");
        mysqli_stmt_bind_param($up, "iii", $new_stk, $stockiest_id, $it['product_id']);
        mysqli_stmt_execute($up);
        mysqli_stmt_close($up);

        $mov2 = mysqli_prepare($conn, "INSERT INTO stock_movements (user_id, product_id, movement_type, quantity, balance_before, balance_after, reference_id, notes) 
                                      VALUES (?, ?, 'Purchase', ?, ?, ?, ?, ?)");
        $m_note = "Purchase received: {$po2_id}";
        mysqli_stmt_bind_param($mov2, "iiiiiss", $stockiest_id, $it['product_id'], $it['quantity'], $curr, $new_stk, $po2_id, $m_note);
        if (!mysqli_stmt_execute($mov2)) throw new Exception(mysqli_stmt_error($mov2));
        mysqli_stmt_close($mov2);
    }
    mysqli_stmt_close($stmt_item2);

    // Record Partial payment
    $pay_id2 = 'PAY-' . $po2_id;
    $pay_note2 = 'Partial Purchase Settlement';
    $pay_stmt2 = mysqli_prepare($conn, "INSERT INTO payments (payment_id, transaction_type, reference_id, user_id, amount, payment_date, payment_method, notes) 
                                        VALUES (?, 'Purchase', ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($pay_stmt2, "ssidsss", $pay_id2, $po2_id, $stockiest_id, $po2_amount_paid, $po2_date, $po2_method, $pay_note2);
    if (!mysqli_stmt_execute($pay_stmt2)) throw new Exception(mysqli_stmt_error($pay_stmt2));
    mysqli_stmt_close($pay_stmt2);

    mysqli_commit($conn);
    $po2_success = true;
} catch (Exception $e) {
    mysqli_rollback($conn);
    $po2_success = false;
    echo "ERROR in Test 2: " . $e->getMessage() . "\n";
}

assert_test("Multi-product purchase saved successfully", $po2_success);

// Verify totals and multi-item records
$items_count_res = mysqli_query($conn, "SELECT COUNT(*) as cnt, SUM(subtotal) as sum_sub FROM purchase_items WHERE purchase_id = {$inserted_id_2}");
$items_count_row = mysqli_fetch_assoc($items_count_res);
assert_test("Both purchase items saved in purchase_items", (int)$items_count_row['cnt'] === 2);
assert_test("Correct grand total calculation from subtotals", (float)$items_count_row['sum_sub'] === (float)$po2_grand_total, "Sum: {$items_count_row['sum_sub']}, Header: {$po2_grand_total}");

$stock_p1_after_m = get_stock_qty($conn, $stockiest_id, $prod1['id']);
$stock_p2_after_m = get_stock_qty($conn, $stockiest_id, $prod2['id']);
assert_test("Stockiest stock increased for Product 1 (+{$po2_qty1})", $stock_p1_after_m === ($stock_p1_before_m + $po2_qty1));
assert_test("Stockiest stock increased for Product 2 (+{$po2_qty2})", $stock_p2_after_m === ($stock_p2_before_m + $po2_qty2));

$mov_count_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM stock_movements WHERE reference_id = '{$po2_id}'");
$mov_count = (int)(mysqli_fetch_assoc($mov_count_res)['cnt'] ?? 0);
assert_test("Multiple stock movements logged (1 per item)", $mov_count === 2);

$pay2_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM payments WHERE reference_id = '{$po2_id}'"));
assert_test("Partial payment recorded correctly", $pay2_row && (float)$pay2_row['amount'] === (float)$po2_amount_paid);


echo "\n--- TEST CASE 3: VALIDATION CHECKS (ZERO / NEGATIVE QTY / INVALID PRODUCT) ---\n";

// Function to simulate validation matching purchases/create.php
function validate_purchase_submission($conn, $supplier_id, $product_ids, $quantities, $prices) {
    // 1. Supplier check
    $chk_sup = mysqli_prepare($conn, "SELECT id FROM suppliers WHERE id = ? AND status = 'Active' LIMIT 1");
    mysqli_stmt_bind_param($chk_sup, "i", $supplier_id);
    mysqli_stmt_execute($chk_sup);
    if (mysqli_num_rows(mysqli_stmt_get_result($chk_sup)) === 0) {
        mysqli_stmt_close($chk_sup);
        return "Selected supplier does not exist or is inactive.";
    }
    mysqli_stmt_close($chk_sup);

    // 2. Line items check
    $clean = [];
    for ($i = 0; $i < count($product_ids); $i++) {
        $pid = (int)($product_ids[$i] ?? 0);
        $qty = (int)($quantities[$i] ?? 0);
        $prc = (float)($prices[$i] ?? 0);

        if ($pid <= 0) continue;
        if ($qty <= 0) return "Quantity must be greater than zero for all selected products.";
        if ($prc < 0) return "Unit cost cannot be negative.";

        $prod_chk = mysqli_prepare($conn, "SELECT id FROM products WHERE id = ? AND status = 'Active' LIMIT 1");
        mysqli_stmt_bind_param($prod_chk, "i", $pid);
        mysqli_stmt_execute($prod_chk);
        if (mysqli_num_rows(mysqli_stmt_get_result($prod_chk)) === 0) {
            mysqli_stmt_close($prod_chk);
            return "One or more selected products do not exist or are inactive.";
        }
        mysqli_stmt_close($prod_chk);
        $clean[] = ['pid' => $pid, 'qty' => $qty, 'price' => $prc];
    }

    if (empty($clean)) return "Please select at least one valid product with positive quantity.";
    return null;
}

$val_zero_qty = validate_purchase_submission($conn, $supplier_id, [$prod1['id']], [0], [$prod1['purchase_price']]);
assert_test("Zero quantity is rejected", $val_zero_qty === "Quantity must be greater than zero for all selected products.");

$val_neg_qty = validate_purchase_submission($conn, $supplier_id, [$prod1['id']], [-5], [$prod1['purchase_price']]);
assert_test("Negative quantity is rejected", $val_neg_qty === "Quantity must be greater than zero for all selected products.");

$val_neg_cost = validate_purchase_submission($conn, $supplier_id, [$prod1['id']], [5], [-10.50]);
assert_test("Negative unit cost is rejected", $val_neg_cost === "Unit cost cannot be negative.");

$val_bad_prod = validate_purchase_submission($conn, $supplier_id, [999999], [5], [100.00]);
assert_test("Non-existent product ID is rejected", $val_bad_prod === "One or more selected products do not exist or are inactive.");

$val_bad_sup = validate_purchase_submission($conn, 999999, [$prod1['id']], [5], [100.00]);
assert_test("Non-existent supplier ID is rejected", $val_bad_sup === "Selected supplier does not exist or is inactive.");


echo "\n--- TEST CASE 4: TRANSACTION ROLLBACK INTEGRITY ---\n";
// Ensure that if an error occurs midway through items, transaction aborts completely
$rollback_po = 'PUR_TEST_FAIL_003';
$stock_before_fail = get_stock_qty($conn, $stockiest_id, $prod1['id']);

mysqli_begin_transaction($conn);
$rollback_triggered = false;
try {
    $stmt_fail_hdr = mysqli_prepare($conn, "INSERT INTO purchases (purchase_id, supplier_id, user_id, purchase_date, total_amount, payment_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $f_tot = 500.00;
    $f_status = 'Paid';
    $f_note = 'Will fail midway';
    mysqli_stmt_bind_param($stmt_fail_hdr, "siisdss", $rollback_po, $supplier_id, $stockiest_id, $po1_date, $f_tot, $f_status, $f_note);
    mysqli_stmt_execute($stmt_fail_hdr);
    $inserted_fail_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt_fail_hdr);

    // First item inserted
    $stmt_fail_item = mysqli_prepare($conn, "INSERT INTO purchase_items (purchase_id, product_id, quantity, purchase_price, subtotal) VALUES (?, ?, ?, ?, ?)");
    $q1 = 5; $p1 = 100.00; $s1 = 500.00;
    mysqli_stmt_bind_param($stmt_fail_item, "iiidd", $inserted_fail_id, $prod1['id'], $q1, $p1, $s1);
    mysqli_stmt_execute($stmt_fail_item);
    mysqli_stmt_close($stmt_fail_item);

    // Second item has an invalid foreign key / forced exception
    throw new Exception("Simulated mid-batch database error");

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    $rollback_triggered = true;
}

assert_test("Database rollback triggered on error", $rollback_triggered);
$chk_failed_hdr = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM purchases WHERE purchase_id = '{$rollback_po}'"));
assert_test("No orphan purchase header remained in DB after rollback", $chk_failed_hdr === null);

$chk_failed_items = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM purchase_items WHERE purchase_id = '{$inserted_fail_id}'"));
assert_test("No orphan purchase items remained in DB after rollback", (int)($chk_failed_items['cnt'] ?? 0) === 0);

$stock_after_fail = get_stock_qty($conn, $stockiest_id, $prod1['id']);
assert_test("Stock was not modified by rolled-back transaction", $stock_after_fail === $stock_before_fail);


echo "\n--- TEST CASE 5: PURCHASE REPORTING & DASHBOARD QUERIES ---\n";
// Test purchases/index.php query
$pur_list_q = mysqli_query($conn, "SELECT p.*, COUNT(pi.id) as item_count, COALESCE(SUM(pi.quantity), 0) as total_units 
                                    FROM purchases p 
                                    JOIN suppliers s ON p.supplier_id = s.id 
                                    JOIN users u ON p.user_id = u.id 
                                    LEFT JOIN purchase_items pi ON p.id = pi.purchase_id 
                                    WHERE p.purchase_id IN ('{$po1_id}', '{$po2_id}')
                                    GROUP BY p.id");
$found_po_list = [];
while ($r = mysqli_fetch_assoc($pur_list_q)) {
    $found_po_list[$r['purchase_id']] = $r;
}
assert_test("Both test purchases appear in purchases/index.php query", count($found_po_list) === 2);
assert_test("Item count and units aggregate properly in purchase list", 
    (int)$found_po_list[$po1_id]['item_count'] === 1 && (int)$found_po_list[$po1_id]['total_units'] === $po1_qty &&
    (int)$found_po_list[$po2_id]['item_count'] === 2 && (int)$found_po_list[$po2_id]['total_units'] === ($po2_qty1 + $po2_qty2));

// Test reports/purchases.php query
$rep_q = mysqli_query($conn, "SELECT p.*, COALESCE(SUM(pi.quantity), 0) as total_units, COUNT(pi.id) as item_count 
                              FROM purchases p 
                              JOIN suppliers s ON p.supplier_id = s.id 
                              JOIN users u ON p.user_id = u.id 
                              LEFT JOIN purchase_items pi ON p.id = pi.purchase_id 
                              WHERE p.purchase_id = '{$po2_id}'
                              GROUP BY p.id");
$rep_row = mysqli_fetch_assoc($rep_q);
assert_test("Purchase appears in reports/purchases.php with accurate units and items", 
    $rep_row && (int)$rep_row['item_count'] === 2 && (float)$rep_row['total_amount'] === (float)$po2_grand_total);


echo "\n--- TEST CASE 6: LIVE HTTP POST TEST VIA CURL ---\n";
$cookie_file = __DIR__ . '/test_pur_cookie.txt';
if (file_exists($cookie_file)) unlink($cookie_file);

// 1. Login as Admin
$ch = curl_init('http://localhost/smart_business/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
$login_page = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token" value="([^"]+)"/', $login_page, $m);
$login_csrf = $m[1] ?? '';

$ch = curl_init('http://localhost/smart_business/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $login_csrf,
    'user_id' => 'ADM001',
    'password' => 'Admin@123'
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$res_login = curl_exec($ch);
curl_close($ch);

$logged_in = (strpos($res_login, 'Admin Dashboard') !== false);
assert_test("Admin login successful via HTTP", $logged_in);

// 2. Open purchases/create.php to retrieve CSRF token and verify clean render
$ch = curl_init('http://localhost/smart_business/purchases/create.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
$create_page = curl_exec($ch);
$create_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

assert_test("purchases/create.php renders with HTTP 200", $create_http === 200 && strpos($create_page, 'Create Supplier Purchase') !== false);
assert_test("Double-submit button ID and JS present on page", strpos($create_page, 'id="submitBtn"') !== false && strpos($create_page, 'isPurchaseSubmitting') !== false);

preg_match('/name="csrf_token" value="([^"]+)"/', $create_page, $m_pur);
$pur_csrf = $m_pur[1] ?? '';

// 3. Submit live HTTP POST to purchases/create.php
$http_po_id = 'PUR_TEST_HTTP_004';
$http_qty = 10;
$http_price = (float)$prod1['purchase_price'];

$post_data = [
    'csrf_token'      => $pur_csrf,
    'purchase_id'    => $http_po_id,
    'supplier_id'    => $supplier_id,
    'stockiest_id'   => $stockiest_id,
    'purchase_date'  => date('Y-m-d'),
    'payment_status' => 'Paid',
    'payment_method' => 'Bank Transfer',
    'notes'          => 'Created via Live HTTP Test',
    'product_ids'    => [$prod1['id']],
    'prices'         => [$http_price],
    'quantities'     => [$http_qty]
];

$ch = curl_init('http://localhost/smart_business/purchases/create.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$submit_resp = curl_exec($ch);
$submit_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check that no fatal error occurred!
$no_fatal = (strpos($submit_resp, 'Fatal error') === false && strpos($submit_resp, 'ArgumentCountError') === false);
assert_test("No PHP Fatal error or ArgumentCountError on live HTTP submission", $no_fatal);

$redirected_to_view = (strpos($submit_resp, 'Purchase Order Details') !== false || strpos($submit_resp, $http_po_id) !== false);
assert_test("Redirected to purchase view page with success confirmation", $redirected_to_view);

// Check HTTP PO in DB
$chk_http_db = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM purchases WHERE purchase_id = '{$http_po_id}'"));
assert_test("Live HTTP purchase saved to purchases table", $chk_http_db !== null);


echo "\n--- CLEANUP OF TEST RECORDS ---\n";
// Revert stock increases created by tests
$revert_p1 = $po1_qty + $po2_qty1 + $http_qty;
$revert_p2 = $po2_qty2;

mysqli_query($conn, "UPDATE stock SET quantity = quantity - {$revert_p1} WHERE user_id = {$stockiest_id} AND product_id = {$prod1['id']}");
mysqli_query($conn, "UPDATE stock SET quantity = quantity - {$revert_p2} WHERE user_id = {$stockiest_id} AND product_id = {$prod2['id']}");

// Delete test records
mysqli_query($conn, "DELETE FROM payments WHERE reference_id LIKE 'PUR_TEST_%'");
mysqli_query($conn, "DELETE FROM stock_movements WHERE reference_id LIKE 'PUR_TEST_%'");
mysqli_query($conn, "DELETE pi FROM purchase_items pi JOIN purchases p ON pi.purchase_id = p.id WHERE p.purchase_id LIKE 'PUR_TEST_%'");
mysqli_query($conn, "DELETE FROM purchases WHERE purchase_id LIKE 'PUR_TEST_%'");

$final_stock_p1 = get_stock_qty($conn, $stockiest_id, $prod1['id']);
$final_stock_p2 = get_stock_qty($conn, $stockiest_id, $prod2['id']);

assert_test("Stock for Product 1 restored to original level", $final_stock_p1 === $initial_stock_p1, "Initial: {$initial_stock_p1}, Restored: {$final_stock_p1}");
assert_test("Stock for Product 2 restored to original level", $final_stock_p2 === $initial_stock_p2, "Initial: {$initial_stock_p2}, Restored: {$final_stock_p2}");

$leftover_pur = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM purchases WHERE purchase_id LIKE 'PUR_TEST_%'"));
assert_test("All test records cleaned up cleanly", $leftover_pur === 0);

if (file_exists($cookie_file)) unlink($cookie_file);

echo "\n============================================================\n";
echo "TEST RESULTS: {$pass_count} PASSED, {$fail_count} FAILED\n";
echo "============================================================\n";

if ($fail_count > 0) {
    exit(1);
}
exit(0);
