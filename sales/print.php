<?php
/**
 * Sales Management - Printable Retail Customer Invoice
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid invoice specified.");
}

$query = "SELECT s.*, 
          COALESCE(c.customer_name, 'Walk-in Retail Customer') as customer_name,
          c.phone as customer_phone, c.email as customer_email, c.address as customer_address,
          u.full_name as seller_name, u.user_id as seller_code, r.role_name as seller_role 
          FROM sales s 
          LEFT JOIN customers c ON s.customer_id = c.id 
          JOIN users u ON s.user_id = u.id 
          JOIN roles r ON u.role_id = r.id 
          WHERE s.id = ? LIMIT 1";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$sale = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$sale) {
    die("Invoice not found.");
}

if (!is_admin() && (int)$sale['user_id'] !== (int)$current_user['id']) {
    die("Access denied.");
}

// Fetch items
$items_query = "SELECT si.*, p.product_id as sku, p.product_name, p.unit, p.brand, c.category_name 
                FROM sale_items si 
                JOIN products p ON si.product_id = p.id 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE si.sale_id = ? 
                ORDER BY si.id ASC";
$i_stmt = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($i_stmt, "i", $id);
mysqli_stmt_execute($i_stmt);
$items_res = mysqli_stmt_get_result($i_stmt);

// Fetch total paid
$pay_res = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE transaction_type = 'Sale' AND reference_id = '{$sale['sale_id']}'");
$total_paid = (float)(mysqli_fetch_assoc($pay_res)['total_paid'] ?? 0);
$balance_due = max(0, (float)$sale['total_amount'] - $total_paid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - <?php echo e($sale['sale_id']); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        body {
            background: #ffffff;
            font-size: 13px;
            color: #333;
            padding: 20px;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
            border: 1px solid #ddd;
            padding: 30px;
            border-radius: 4px;
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 2px solid #2b5797;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .invoice-header h1 {
            font-size: 22px;
            color: #1a365d;
            margin-bottom: 4px;
        }
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 25px;
        }
        .invoice-party {
            width: 48%;
        }
        .invoice-party h3 {
            font-size: 13px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 1px solid #eee;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .print-btn-bar {
            text-align: right;
            max-width: 800px;
            margin: 0 auto 15px;
        }
        @media print {
            .print-btn-bar {
                display: none;
            }
            .invoice-box {
                border: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="print-btn-bar">
        <button onclick="window.print()" class="btn btn-primary btn-sm">&#128438; Print Customer Invoice</button>
        <button onclick="window.close()" class="btn btn-secondary btn-sm">Close Window</button>
    </div>

    <div class="invoice-box">
        <div class="invoice-header">
            <div>
                <h1>ONLINE SMART BUSINESS</h1>
                <div style="color: #666; font-size: 12px;">Stockiest &bull; Distributor &bull; Retailer Network</div>
                <div style="font-weight: bold; margin-top: 6px; color: #2e7d32;">TAX INVOICE / CASH BILL</div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 18px; font-weight: bold; color: #1a365d;"><?php echo e($sale['sale_id']); ?></div>
                <div style="color: #666;">Date: <?php echo date('d M Y', strtotime($sale['sale_date'])); ?></div>
                <div style="color: #666; font-size: 11px;">Time: <?php echo date('h:i A', strtotime($sale['created_at'])); ?></div>
            </div>
        </div>

        <div class="invoice-details">
            <div class="invoice-party">
                <h3>Customer Information</h3>
                <strong><?php echo e($sale['customer_name']); ?></strong><br>
                <?php if (!empty($sale['customer_phone'])): ?>
                    Phone: <?php echo e($sale['customer_phone']); ?><br>
                <?php endif; ?>
                <?php if (!empty($sale['customer_email'])): ?>
                    Email: <?php echo e($sale['customer_email']); ?><br>
                <?php endif; ?>
                <?php if (!empty($sale['customer_address'])): ?>
                    Address: <?php echo nl2br(e($sale['customer_address'])); ?>
                <?php else: ?>
                    Over-the-Counter Customer
                <?php endif; ?>
            </div>

            <div class="invoice-party" style="text-align: right;">
                <h3>Store / Billed By</h3>
                <strong><?php echo e($sale['seller_name']); ?></strong><br>
                Account: <?php echo e($sale['seller_code']); ?> (<?php echo e($sale['seller_role']); ?>)<br>
                Payment Mode: <strong><?php echo e($sale['payment_method']); ?></strong><br>
                Status: <strong><?php echo e($sale['payment_status']); ?></strong>
            </div>
        </div>

        <table class="data-table" style="width: 100%; margin-bottom: 25px;">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 100px;">SKU</th>
                    <th>Item Description</th>
                    <th style="text-align: right; width: 110px;">Unit Price (₹)</th>
                    <th style="text-align: right; width: 90px;">Quantity</th>
                    <th style="text-align: right; width: 130px;">Total (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $line = 1;
                $calc_qty = 0;
                while ($it = mysqli_fetch_assoc($items_res)): 
                    $calc_qty += (int)$it['quantity'];
                ?>
                    <tr>
                        <td><?php echo $line++; ?></td>
                        <td><?php echo e($it['sku']); ?></td>
                        <td>
                            <strong><?php echo e($it['product_name']); ?></strong>
                            <?php if (!empty($it['brand'])): ?>
                                <span style="font-size: 11px; color:#666;">(<?php echo e($it['brand']); ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">₹<?php echo number_format($it['selling_price'], 2); ?></td>
                        <td style="text-align: right; font-weight: bold;"><?php echo number_format($it['quantity']); ?> <?php echo e($it['unit']); ?></td>
                        <td style="text-align: right; font-weight: bold;">₹<?php echo number_format($it['subtotal'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc; font-weight: bold;">
                    <td colspan="4" style="text-align: right;">Subtotal (<?php echo number_format($calc_qty); ?> items):</td>
                    <td colspan="2" style="text-align: right; font-size: 15px; color: #1a365d;">
                        ₹<?php echo number_format($sale['total_amount'], 2); ?>
                    </td>
                </tr>
                <tr style="background: #f8fafc; font-weight: bold;">
                    <td colspan="4" style="text-align: right;">Amount Paid:</td>
                    <td colspan="2" style="text-align: right; color: #2e7d32;">
                        ₹<?php echo number_format($total_paid, 2); ?>
                    </td>
                </tr>
                <?php if ($balance_due > 0): ?>
                    <tr style="background: #f8fafc; font-weight: bold;">
                        <td colspan="4" style="text-align: right; color: #c9302c;">Balance Due:</td>
                        <td colspan="2" style="text-align: right; color: #c9302c; font-size: 15px;">
                            ₹<?php echo number_format($balance_due, 2); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tfoot>
        </table>

        <?php if (!empty($sale['notes'])): ?>
            <div style="margin-bottom: 25px; padding: 10px; background: #fdfdfd; border: 1px solid #eee; border-radius: 3px;">
                <strong>Payment / Billing Notes:</strong> <?php echo nl2br(e($sale['notes'])); ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 35px; border-top: 1px dashed #ccc; padding-top: 15px; text-align: center; color: #666; font-size: 12px;">
            Thank you for your business! Please retain this invoice for your records and returns.
        </div>
    </div>
</body>
</html>
