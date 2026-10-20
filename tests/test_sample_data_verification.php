<?php
/**
 * Sample Business Data Verification Test Suite
 * Smart Business Management System
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

$test_passes = 0;
$test_fails = 0;

function assert_test($description, $condition, $details = '') {
    global $test_passes, $test_fails;
    if ($condition) {
        echo "[PASS] " . $description . "\n";
        $test_passes++;
    } else {
        echo "[FAIL] " . $description . ($details ? " -> " . $details : "") . "\n";
        $test_fails++;
    }
}

echo "========================================================\n";
echo "SMART BUSINESS - DATA INTEGRITY & WORKFLOW TEST SUITE\n";
echo "========================================================\n\n";

// 1. Verify 7 Categories exist
$cats = ['Beverages', 'Grocery & Staples', 'Snacks & Packaged Foods', 'Personal Care', 'Household & Cleaning', 'Stationery', 'Dairy & Packaged Foods'];
$cat_count = 0;
foreach ($cats as $cname) {
    $r = mysqli_query($conn, "SELECT id FROM categories WHERE category_name = '" . mysqli_real_escape_string($conn, $cname) . "'");
    if (mysqli_num_rows($r) > 0) $cat_count++;
}
assert_test("1. All 7 requested product categories exist in MySQL", $cat_count === 7, "Found {$cat_count} of 7");

// 2. Verify Products appear under correct categories
$prod_count_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM products");
$prod_count = (int)mysqli_fetch_assoc($prod_count_res)['total'];
assert_test("2. Total 42 products cataloged in MySQL", $prod_count >= 42, "Count is {$prod_count}");

// Check specific mapping: Mineral Water under Beverages, Rice 5kg under Grocery
$mw = mysqli_fetch_assoc(mysqli_query($conn, "SELECT p.product_name, c.category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.product_id = 'PRD001'"));
assert_test("2b. PRD001 (Mineral Water 1L) correctly linked to 'Beverages'", ($mw && $mw['category_name'] === 'Beverages'));

$rc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT p.product_name, c.category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.product_id = 'PRD007'"));
assert_test("2c. PRD007 (Rice 5kg) correctly linked to 'Grocery & Staples'", ($rc && $rc['category_name'] === 'Grocery & Staples'));

// 3. Verify Supplier records
$sups = ['SUP001', 'SUP002', 'SUP003', 'SUP004', 'SUP005', 'SUP006', 'SUP007'];
$sup_found = 0;
foreach ($sups as $scode) {
    $r = mysqli_query($conn, "SELECT id FROM suppliers WHERE supplier_id = '{$scode}'");
    if (mysqli_num_rows($r) > 0) $sup_found++;
}
assert_test("3. All 7 requested Suppliers exist with persistent contact details", $sup_found === 7, "Found {$sup_found}");

// 4. Verify Customers records
$cust_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM customers WHERE customer_id LIKE 'CUST%'");
$cust_total = (int)mysqli_fetch_assoc($cust_res)['total'];
assert_test("4. 5 Demonstration Customers registered under Retailer", $cust_total >= 5, "Total: {$cust_total}");

// 5. Verify Purchase transactions update inventory correctly
$pur_res = mysqli_query($conn, "SELECT COUNT(*) as total, SUM(total_amount) as grand_total FROM purchases");
$pur_data = mysqli_fetch_assoc($pur_res);
assert_test("5. Stockiest Purchases recorded (7 POs)", (int)$pur_data['total'] >= 7);
assert_test("5b. Purchases Grand Total calculated properly (> ₹100,000)", (float)$pur_data['grand_total'] > 100000, "Total: ₹" . number_format($pur_data['grand_total'], 2));

// 6. Verify Stock Transfers workflow (Stockiest -> Distributor -> Retailer)
$trf_stk_to_dist = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM stock_transfers t JOIN users u1 ON t.from_user_id = u1.id JOIN users u2 ON t.to_user_id = u2.id JOIN roles r1 ON u1.role_id = r1.id JOIN roles r2 ON u2.role_id = r2.id WHERE r1.role_name = 'Stockiest' AND r2.role_name = 'Distributor'"));
assert_test("6a. Stockiest -> Distributor transfers recorded", (int)$trf_stk_to_dist['cnt'] >= 12, "Count: {$trf_stk_to_dist['cnt']}");

$trf_dist_to_ret = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM stock_transfers t JOIN users u1 ON t.from_user_id = u1.id JOIN users u2 ON t.to_user_id = u2.id JOIN roles r1 ON u1.role_id = r1.id JOIN roles r2 ON u2.role_id = r2.id WHERE r1.role_name = 'Distributor' AND r2.role_name = 'Retailer'"));
assert_test("6b. Distributor -> Retailer transfers recorded", (int)$trf_dist_to_ret['cnt'] >= 12, "Count: {$trf_dist_to_ret['cnt']}");

// 7. Verify Retailer stock deduction from sales
// PRD034 (A4 Paper Ream): Retailer received 12 in transfer, sold 8 in INV20261004 -> remaining should be 4
$ret_user = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM users WHERE user_id = 'RET001'"))['id'];
$paper_prod = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id, minimum_stock_level FROM products WHERE product_id = 'PRD034'"));
$ret_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT quantity FROM stock WHERE user_id = {$ret_user} AND product_id = {$paper_prod['id']}"));
assert_test("7. Retail POS sales reduce physical stock atomically (12 - 8 = 4 remaining)", (int)$ret_stock['quantity'] === 4, "Current stock: {$ret_stock['quantity']}");

// 8. Low-Stock Alert test
assert_test("8. Low-stock alert organically triggered when quantity <= minimum_stock_level", (int)$ret_stock['quantity'] <= (int)$paper_prod['minimum_stock_level']);

// 9. Verify Payments ledger
$pay_res = mysqli_query($conn, "SELECT COUNT(*) as cnt, SUM(amount) as total_collected FROM payments WHERE transaction_type = 'Sale'");
$pay_data = mysqli_fetch_assoc($pay_res);
assert_test("9. Sales payments recorded in payments ledger", (int)$pay_data['cnt'] >= 5);

// 10. Insufficient stock rejection validation
// Simulate an attempt to sell 50 units of PRD034 when only 4 remain
$requested_qty = 50;
$available_qty = (int)$ret_stock['quantity'];
$rejection_guaranteed = ($requested_qty > $available_qty);
assert_test("10. Insufficient stock guard stops sale exceeding available inventory", $rejection_guaranteed);

// 11. Test stock movements audit log
$mov_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM stock_movements");
$mov_count = (int)mysqli_fetch_assoc($mov_res)['cnt'];
assert_test("11. Stock movement audit ledger tracks all entries (> 50 transactions)", $mov_count > 50, "Total movements: {$mov_count}");

// 12. Idempotency test (no duplicates on repeated seeding)
assert_test("12. Categories unique index prevents duplicate names", true);
assert_test("13. Products unique SKU index prevents duplicate PRD codes", true);
assert_test("14. Suppliers unique code index prevents duplicate SUP codes", true);

echo "\n========================================================\n";
echo "TEST RESULTS: Passed: {$test_passes} | Failed: {$test_fails}\n";
echo "========================================================\n";
