<?php
/**
 * Stock Transfers - View Transfer Note / Challan
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Transfer Challan Details';
$active_menu = 'transfers';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    set_flash('danger', "Invalid transfer specified.");
    header("Location: index.php");
    exit;
}

$query = "SELECT st.*, 
          p.product_id as sku, p.product_name, p.brand, p.unit, p.purchase_price, p.selling_price, c.category_name,
          u_from.full_name as from_name, u_from.user_id as from_code, u_from.email as from_email, r_from.role_name as from_role,
          u_to.full_name as to_name, u_to.user_id as to_code, u_to.email as to_email, r_to.role_name as to_role 
          FROM stock_transfers st 
          JOIN products p ON st.product_id = p.id 
          LEFT JOIN categories c ON p.category_id = c.id 
          JOIN users u_from ON st.from_user_id = u_from.id 
          JOIN roles r_from ON u_from.role_id = r_from.id 
          JOIN users u_to ON st.to_user_id = u_to.id 
          JOIN roles r_to ON u_to.role_id = r_to.id 
          WHERE st.id = ? LIMIT 1";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$transfer = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$transfer) {
    set_flash('danger', "Transfer record not found.");
    header("Location: index.php");
    exit;
}

// Access check: Only involved parties or admin
if (!is_admin() && (int)$transfer['from_user_id'] !== (int)$current_user['id'] && (int)$transfer['to_user_id'] !== (int)$current_user['id']) {
    set_flash('danger', "You do not have permission to view this transfer challan.");
    header("Location: index.php");
    exit;
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Stock Transfer: <?php echo e($transfer['transfer_id']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Transfers / Challan</div>
    </div>
    <div style="display: flex; gap: 8px;">
        <button onclick="window.print()" class="btn btn-secondary btn-sm">&#128438; Print Delivery Challan</button>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Transfers</a>
    </div>
</div>

<div class="page-content">
    <div class="card" style="max-width: 820px; margin: 0 auto 20px;">
        <div class="card-header" style="background: #ffffff; border-bottom: 2px solid #2b5797;">
            <div>
                <strong style="font-size: 16px; color: #1a365d;">INTERNAL STOCK TRANSFER CHALLAN</strong>
                <div style="font-size: 12px; color: #666;">Online Smart Business Distribution Network</div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 16px; font-weight: bold; color: #2b5797;"><?php echo e($transfer['transfer_id']); ?></div>
                <div style="font-size: 12px; color: #555;">Date: <?php echo date('d M Y', strtotime($transfer['transfer_date'])); ?></div>
            </div>
        </div>
        <div class="card-body">
            <!-- Party Grid -->
            <div class="grid-row" style="margin-bottom: 20px;">
                <div class="grid-col-6" style="background: #fafbfc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 4px;">
                    <div style="font-size: 11px; font-weight: bold; color: #888; text-transform: uppercase; margin-bottom: 6px;">DISPATCHED FROM (SOURCE)</div>
                    <div style="font-size: 15px; font-weight: bold; color: #1a365d;"><?php echo e($transfer['from_name']); ?></div>
                    <div style="margin-top: 4px;">
                        <span class="badge-role badge-<?php echo strtolower($transfer['from_role']); ?>"><?php echo e($transfer['from_role']); ?></span>
                        <span style="color: #666; font-size: 12px; margin-left: 6px;">ID: <?php echo e($transfer['from_code']); ?></span>
                    </div>
                    <div style="color: #555; font-size: 12px; margin-top: 4px;"><?php echo e($transfer['from_email']); ?></div>
                </div>

                <div class="grid-col-6" style="background: #fafbfc; padding: 15px; border: 1px solid #e2e8f0; border-radius: 4px;">
                    <div style="font-size: 11px; font-weight: bold; color: #888; text-transform: uppercase; margin-bottom: 6px;">CONSIGNED TO (DESTINATION)</div>
                    <div style="font-size: 15px; font-weight: bold; color: #1a365d;"><?php echo e($transfer['to_name']); ?></div>
                    <div style="margin-top: 4px;">
                        <span class="badge-role badge-<?php echo strtolower($transfer['to_role']); ?>"><?php echo e($transfer['to_role']); ?></span>
                        <span style="color: #666; font-size: 12px; margin-left: 6px;">ID: <?php echo e($transfer['to_code']); ?></span>
                    </div>
                    <div style="color: #555; font-size: 12px; margin-top: 4px;"><?php echo e($transfer['to_email']); ?></div>
                </div>
            </div>

            <!-- Product Items Table -->
            <table class="data-table" style="margin-bottom: 20px;">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="width: 120px;">Product Code</th>
                        <th>Product Description</th>
                        <th>Category</th>
                        <th style="text-align: right; width: 140px;">Quantity Dispatched</th>
                        <th style="width: 110px; text-align: center;">Transfer Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td><strong><?php echo e($transfer['sku']); ?></strong></td>
                        <td>
                            <strong><?php echo e($transfer['product_name']); ?></strong>
                            <?php if (!empty($transfer['brand'])): ?>
                                <span style="font-size: 11px; color:#666;">&bull; Brand: <?php echo e($transfer['brand']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($transfer['category_name'] ?? '—'); ?></td>
                        <td style="text-align: right; font-size: 15px; font-weight: bold; color: #2e7d32;">
                            <?php echo number_format($transfer['quantity']); ?> <?php echo e($transfer['unit']); ?>
                        </td>
                        <td style="text-align: center;">
                            <span class="badge badge-success">&check; <?php echo e($transfer['status']); ?></span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <?php if (!empty($transfer['remarks'])): ?>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; padding: 12px; border-radius: 3px; margin-bottom: 25px;">
                    <strong>Waybill / Transit Remarks:</strong><br>
                    <span style="color: #444;"><?php echo nl2br(e($transfer['remarks'])); ?></span>
                </div>
            <?php endif; ?>

            <div style="border-top: 1px dashed #ccc; padding-top: 15px; font-size: 12px; color: #777;">
                System Transaction Timestamp: <?php echo date('d M Y, h:i:s A', strtotime($transfer['created_at'])); ?> &bull; Database Atomic Relocation Verified
            </div>

            <!-- Signatures -->
            <div style="margin-top: 50px; display: flex; justify-content: space-between; text-align: center;">
                <div style="width: 220px; border-top: 1px solid #333; padding-top: 5px; font-size: 12px;">
                    Dispatched by (<?php echo e($transfer['from_role']); ?>)
                </div>
                <div style="width: 220px; border-top: 1px solid #333; padding-top: 5px; font-size: 12px;">
                    Received by (<?php echo e($transfer['to_role']); ?>)
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
