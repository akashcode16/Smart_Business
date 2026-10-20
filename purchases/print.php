<?php
/**
 * Purchase Management - Printable Purchase Order
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Invalid purchase specified.");
}

$query = "SELECT p.*, s.supplier_id as vendor_code, s.supplier_name, s.company_name, s.phone as supplier_phone, s.email as supplier_email, s.address as supplier_address,
          u.full_name as purchaser_name, u.user_id as purchaser_code 
          FROM purchases p 
          JOIN suppliers s ON p.supplier_id = s.id 
          JOIN users u ON p.user_id = u.id 
          WHERE p.id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$purchase = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$purchase) {
    die("Purchase order not found.");
}

if (!is_admin() && (int)$purchase['user_id'] !== (int)$current_user['id']) {
    die("Access denied.");
}

// Fetch items
$items_query = "SELECT pi.*, p.product_id as sku, p.product_name, p.unit, p.brand, c.category_name 
                FROM purchase_items pi 
                JOIN products p ON pi.product_id = p.id 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE pi.purchase_id = ? 
                ORDER BY pi.id ASC";
$i_stmt = mysqli_prepare($conn, $items_query);
mysqli_stmt_bind_param($i_stmt, "i", $id);
mysqli_stmt_execute($i_stmt);
$items_result = mysqli_stmt_get_result($i_stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order - <?php echo e($purchase['purchase_id']); ?></title>
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
        <button onclick="window.print()" class="btn btn-primary btn-sm">&#128438; Print This Order</button>
        <button onclick="window.close()" class="btn btn-secondary btn-sm">Close Window</button>
    </div>

    <div class="invoice-box">
        <div class="invoice-header">
            <div>
                <h1>ONLINE SMART BUSINESS</h1>
                <div style="color: #666; font-size: 12px;">Stockiest &bull; Distributor &bull; Retailer Network</div>
                <div style="font-weight: bold; margin-top: 6px; color: #2b5797;">PURCHASE ORDER</div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 18px; font-weight: bold; color: #1a365d;"><?php echo e($purchase['purchase_id']); ?></div>
                <div style="color: #666;">Date: <?php echo date('d M Y', strtotime($purchase['purchase_date'])); ?></div>
                <div style="margin-top: 4px;">Status: <strong><?php echo e($purchase['payment_status']); ?></strong></div>
            </div>
        </div>

        <div class="invoice-details">
            <div class="invoice-party">
                <h3>Supplier / Vendor</h3>
                <strong><?php echo e($purchase['supplier_name']); ?></strong><br>
                <?php if (!empty($purchase['company_name'])): ?>
                    <?php echo e($purchase['company_name']); ?><br>
                <?php endif; ?>
                Phone: <?php echo e($purchase['supplier_phone']); ?><br>
                <?php if (!empty($purchase['supplier_email'])): ?>
                    Email: <?php echo e($purchase['supplier_email']); ?><br>
                <?php endif; ?>
                <?php if (!empty($purchase['supplier_address'])): ?>
                    Address: <?php echo nl2br(e($purchase['supplier_address'])); ?>
                <?php endif; ?>
            </div>

            <div class="invoice-party" style="text-align: right;">
                <h3>Inward Account</h3>
                <strong><?php echo e($purchase['purchaser_name']); ?></strong><br>
                Account ID: <?php echo e($purchase['purchaser_code']); ?><br>
                Warehouse Delivery Location<br>
                Inventory Credited: <?php echo date('d M Y, h:i A', strtotime($purchase['created_at'])); ?>
            </div>
        </div>

        <table class="data-table" style="width: 100%; margin-bottom: 25px;">
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 100px;">SKU</th>
                    <th>Product Description</th>
                    <th style="text-align: right; width: 110px;">Unit Cost</th>
                    <th style="text-align: right; width: 90px;">Quantity</th>
                    <th style="text-align: right; width: 120px;">Line Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $idx = 1;
                $tot_qty = 0;
                while ($it = mysqli_fetch_assoc($items_result)): 
                    $tot_qty += (int)$it['quantity'];
                ?>
                    <tr>
                        <td><?php echo $idx++; ?></td>
                        <td><?php echo e($it['sku']); ?></td>
                        <td>
                            <strong><?php echo e($it['product_name']); ?></strong>
                            <?php if (!empty($it['brand'])): ?>
                                <span style="font-size: 11px; color:#666;">(<?php echo e($it['brand']); ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">₹<?php echo number_format($it['purchase_price'], 2); ?></td>
                        <td style="text-align: right; font-weight: bold;"><?php echo number_format($it['quantity']); ?> <?php echo e($it['unit']); ?></td>
                        <td style="text-align: right; font-weight: bold;">₹<?php echo number_format($it['subtotal'], 2); ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr style="background: #f8fafc; font-weight: bold;">
                    <td colspan="4" style="text-align: right;">Total Units &amp; Amount:</td>
                    <td style="text-align: right;"><?php echo number_format($tot_qty); ?></td>
                    <td style="text-align: right; font-size: 15px; color: #1a365d;">₹<?php echo number_format($purchase['total_amount'], 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <?php if (!empty($purchase['notes'])): ?>
            <div style="margin-bottom: 25px; padding: 10px; background: #fdfdfd; border: 1px solid #eee; border-radius: 3px;">
                <strong>Remarks / Notes:</strong> <?php echo nl2br(e($purchase['notes'])); ?>
            </div>
        <?php endif; ?>

        <div style="margin-top: 40px; display: flex; justify-content: space-between; text-align: center;">
            <div style="width: 200px; border-top: 1px solid #aaa; padding-top: 6px;">
                Authorized Inward Officer
            </div>
            <div style="width: 200px; border-top: 1px solid #aaa; padding-top: 6px;">
                Supplier / Vendor Signature
            </div>
        </div>
    </div>
</body>
</html>
