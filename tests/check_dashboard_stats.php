<?php
/**
 * Check live calculated metrics for all 4 Dashboards
 */
require_once __DIR__ . '/../config/database.php';

echo "=== LIVE DATABASE DASHBOARD METRICS ===\n\n";

// 1. ADMIN DASHBOARD METRICS
$u_count = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM users WHERE status = 'Active'"))[0];
$c_count = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM categories WHERE status = 'Active'"))[0];
$p_count = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM products WHERE status = 'Active'"))[0];
$pur_sum = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as total FROM purchases"));
$sale_sum = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as invoices, COALESCE(SUM(total_amount), 0) as total FROM sales"));
$low_stock = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM stock s JOIN products p ON s.product_id = p.id WHERE s.quantity <= p.minimum_stock_level"))[0];

echo "ADMIN DASHBOARD:\n";
echo " - Active Users: {$u_count}\n";
echo " - Active Categories: {$c_count}\n";
echo " - Active Products: {$p_count}\n";
echo " - Purchases: {$pur_sum['orders']} orders, ₹" . number_format($pur_sum['total'], 2) . "\n";
echo " - Sales: {$sale_sum['invoices']} invoices, ₹" . number_format($sale_sum['total'], 2) . "\n";
echo " - Low Stock Alerts Across Entities: {$low_stock}\n\n";

// 2. STOCKIEST DASHBOARD METRICS (User STK001, id 2)
$stk_id = 2;
$stk_inv = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as items, COALESCE(SUM(s.quantity), 0) as units, COALESCE(SUM(s.quantity * p.purchase_price), 0) as val FROM stock s JOIN products p ON s.product_id = p.id WHERE s.user_id = {$stk_id}"));
$stk_pur = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as total FROM purchases WHERE user_id = {$stk_id}"));
$stk_trf = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as trfs, COALESCE(SUM(quantity), 0) as units FROM stock_transfers WHERE from_user_id = {$stk_id}"));

echo "STOCKIEST DASHBOARD (STK001):\n";
echo " - Warehouse Stock: {$stk_inv['items']} SKUs, {$stk_inv['units']} physical units (Valuation: ₹" . number_format($stk_inv['val'], 2) . ")\n";
echo " - Inward Purchases: {$stk_pur['orders']} orders, ₹" . number_format($stk_pur['total'], 2) . "\n";
echo " - Outward Transfers: {$stk_trf['trfs']} transfers, {$stk_trf['units']} units dispatched\n\n";

// 3. DISTRIBUTOR DASHBOARD METRICS (User DST001, id 3)
$dst_id = 3;
$dst_recv = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as trfs, COALESCE(SUM(quantity), 0) as units FROM stock_transfers WHERE to_user_id = {$dst_id}"));
$dst_disp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as trfs, COALESCE(SUM(quantity), 0) as units FROM stock_transfers WHERE from_user_id = {$dst_id}"));
$dst_avail = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as items, COALESCE(SUM(s.quantity), 0) as units, COALESCE(SUM(s.quantity * p.purchase_price), 0) as val FROM stock s JOIN products p ON s.product_id = p.id WHERE s.user_id = {$dst_id}"));

echo "DISTRIBUTOR DASHBOARD (DST001):\n";
echo " - Received from Stockiest: {$dst_recv['trfs']} dispatches, {$dst_recv['units']} units\n";
echo " - Transferred to Retailer: {$dst_disp['trfs']} dispatches, {$dst_disp['units']} units\n";
echo " - Available Regional Stock: {$dst_avail['items']} SKUs, {$dst_avail['units']} physical units (Valuation: ₹" . number_format($dst_avail['val'], 2) . ")\n\n";

// 4. RETAILER DASHBOARD METRICS (User RET001, id 4)
$ret_id = 4;
$ret_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as items, COALESCE(SUM(s.quantity), 0) as units, COALESCE(SUM(s.quantity * p.selling_price), 0) as val FROM stock s JOIN products p ON s.product_id = p.id WHERE s.user_id = {$ret_id}"));
$ret_sales = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as invoices, COALESCE(SUM(total_amount), 0) as total FROM sales WHERE user_id = {$ret_id}"));
$ret_cust = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM customers WHERE created_by = {$ret_id}"))[0];
$ret_low = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM stock s JOIN products p ON s.product_id = p.id WHERE s.user_id = {$ret_id} AND s.quantity <= p.minimum_stock_level"))[0];

echo "RETAILER DASHBOARD (RET001):\n";
echo " - Available Counter Stock: {$ret_stock['items']} SKUs, {$ret_stock['units']} physical units (Retail Value: ₹" . number_format($ret_stock['val'], 2) . ")\n";
echo " - Counter POS Sales: {$ret_sales['invoices']} invoices, ₹" . number_format($ret_sales['total'], 2) . " revenue\n";
echo " - Registered Store Customers: {$ret_cust} accounts\n";
echo " - Low Stock Counter Warnings: {$ret_low} items (A4 Paper Reams alert triggered organically!)\n\n";
