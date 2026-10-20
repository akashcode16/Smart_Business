<?php
/**
 * Sales Management - Record Payment against Invoice
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Record Invoice Payment';
$active_menu = 'sales';

$error_message = '';
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', "Invalid invoice specified.");
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT s.*, COALESCE(c.customer_name, 'Walk-in Customer') as customer_name 
                               FROM sales s 
                               LEFT JOIN customers c ON s.customer_id = c.id 
                               WHERE s.id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$sale = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$sale) {
    set_flash('danger', "Invoice not found.");
    header("Location: index.php");
    exit;
}

// Access check
if (!is_admin() && (int)$sale['user_id'] !== (int)$current_user['id']) {
    set_flash('danger', "Access denied.");
    header("Location: index.php");
    exit;
}

// Calculate existing payments
$pay_res = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE transaction_type = 'Sale' AND reference_id = '{$sale['sale_id']}'");
$total_paid = (float)(mysqli_fetch_assoc($pay_res)['total_paid'] ?? 0);
$grand_total = (float)$sale['total_amount'];
$balance_due = max(0, $grand_total - $total_paid);

if ($balance_due <= 0) {
    set_flash('success', "Invoice {$sale['sale_id']} is already settled in full.");
    header("Location: view.php?id=" . $id);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $pay_amount = (float)($_POST['amount'] ?? 0);
        $pay_date = trim($_POST['payment_date'] ?? date('Y-m-d'));
        $pay_method = in_array($_POST['payment_method'] ?? '', ['Cash', 'Card', 'UPI', 'Bank Transfer', 'Credit']) ? $_POST['payment_method'] : 'Cash';
        $pay_notes = trim($_POST['notes'] ?? '');

        if ($pay_amount <= 0) {
            $error_message = "Payment amount must be greater than zero.";
        } elseif ($pay_amount > $balance_due) {
            $error_message = "Payment amount (₹" . number_format($pay_amount, 2) . ") cannot exceed the outstanding balance of ₹" . number_format($balance_due, 2) . ".";
        } else {
            // Begin transaction
            mysqli_begin_transaction($conn);

            try {
                // Generate payment ID
                $pay_count_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM payments WHERE reference_id = '{$sale['sale_id']}'");
                $p_cnt = (int)(mysqli_fetch_assoc($pay_count_res)['cnt'] ?? 0) + 1;
                $payment_id = 'PAY-' . $sale['sale_id'] . '-P' . $p_cnt;

                // Insert payment record
                $ins_pay = mysqli_prepare($conn, "INSERT INTO payments (payment_id, transaction_type, reference_id, user_id, amount, payment_date, payment_method, notes) 
                                                   VALUES (?, 'Sale', ?, ?, ?, ?, ?, ?)");
                $uid = (int)$current_user['id'];
                mysqli_stmt_bind_param($ins_pay, "ssisdss", $payment_id, $sale['sale_id'], $uid, $pay_amount, $pay_date, $pay_method, $pay_notes);
                mysqli_stmt_execute($ins_pay);
                mysqli_stmt_close($ins_pay);

                // Update sale payment_status
                $new_total_paid = $total_paid + $pay_amount;
                $new_status = ($new_total_paid >= $grand_total) ? 'Paid' : 'Partial';

                $up_sale = mysqli_prepare($conn, "UPDATE sales SET payment_status = ? WHERE id = ?");
                mysqli_stmt_bind_param($up_sale, "si", $new_status, $id);
                mysqli_stmt_execute($up_sale);
                mysqli_stmt_close($up_sale);

                mysqli_commit($conn);

                set_flash('success', "Payment of ₹" . number_format($pay_amount, 2) . " recorded successfully. Status updated to {$new_status}.");
                header("Location: view.php?id=" . $id);
                exit;

            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_message = "Payment transaction failed: " . $e->getMessage();
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Record Payment: <?php echo e($sale['sale_id']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Sales / Record Payment</div>
    </div>
    <div>
        <a href="view.php?id=<?php echo e($id); ?>" class="btn btn-secondary btn-sm">&larr; Back to Invoice</a>
    </div>
</div>

<div class="page-content">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo e($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 600px;">
        <div class="card-header">
            <span>Payment Receipt &bull; <?php echo e($sale['sale_id']); ?></span>
            <span class="badge badge-warning">Outstanding Balance</span>
        </div>
        <div class="card-body">
            <div style="background: #fafbfc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 4px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span>Customer:</span>
                    <strong><?php echo e($sale['customer_name']); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span>Invoice Total:</span>
                    <strong>₹<?php echo number_format($grand_total, 2); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span>Previously Paid:</span>
                    <strong style="color: #2e7d32;">₹<?php echo number_format($total_paid, 2); ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; border-top: 1px dashed #ccc; padding-top: 6px; font-size: 15px;">
                    <span style="color: #c9302c; font-weight: bold;">Remaining Balance:</span>
                    <strong style="color: #c9302c;">₹<?php echo number_format($balance_due, 2); ?></strong>
                </div>
            </div>

            <form action="record_payment.php?id=<?php echo e($id); ?>" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="form-group">
                    <label for="amount">Payment Amount (₹) <span style="color:#c9302c;">*</span></label>
                    <input type="number" step="0.01" min="0.01" max="<?php echo e($balance_due); ?>" id="amount" name="amount" class="form-control" 
                           value="<?php echo isset($_POST['amount']) ? e($_POST['amount']) : number_format($balance_due, 2, '.', ''); ?>" required>
                    <small style="color: #666; font-size: 11px;">Max allowed: ₹<?php echo number_format($balance_due, 2); ?></small>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="payment_date">Payment Date <span style="color:#c9302c;">*</span></label>
                            <input type="date" id="payment_date" name="payment_date" class="form-control" 
                                   value="<?php echo isset($_POST['payment_date']) ? e($_POST['payment_date']) : date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="payment_method">Payment Mode <span style="color:#c9302c;">*</span></label>
                            <select id="payment_method" name="payment_method" class="form-control" required>
                                <option value="Cash">Cash</option>
                                <option value="UPI">UPI</option>
                                <option value="Card">Card</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Credit">Credit</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Payment Reference / Notes</label>
                    <textarea id="notes" name="notes" class="form-control" rows="2" placeholder="e.g. Transaction Ref, Cheque Number..."></textarea>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">&check; Confirm &amp; Save Payment</button>
                    <a href="view.php?id=<?php echo e($id); ?>" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
