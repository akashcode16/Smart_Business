<?php
/**
 * Customer Management - Edit Customer
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Edit Customer';
$active_menu = 'customers';

$error_message = '';
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', "Invalid customer specified.");
    header("Location: index.php");
    exit;
}

// Fetch customer
$stmt = mysqli_prepare($conn, "SELECT * FROM customers WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$customer) {
    set_flash('danger', "Customer not found.");
    header("Location: index.php");
    exit;
}

// Access check: If not Admin, must be creator
if (!is_admin() && !empty($customer['created_by']) && (int)$customer['created_by'] !== (int)$current_user['id']) {
    set_flash('danger', "You do not have permission to edit this customer.");
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $customer_name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $address = trim($_POST['address'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

        if (empty($customer_name) || empty($phone)) {
            $error_message = "Customer Name and Phone number are required.";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            $update = mysqli_prepare($conn, "UPDATE customers SET customer_name = ?, phone = ?, email = ?, address = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($update, "sssssi", $customer_name, $phone, $email, $address, $status, $id);
            if (mysqli_stmt_execute($update)) {
                mysqli_stmt_close($update);
                set_flash('success', "Customer '{$customer_name}' updated successfully.");
                header("Location: index.php");
                exit;
            } else {
                $error_message = "Failed to update customer: " . mysqli_error($conn);
                mysqli_stmt_close($update);
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Edit Customer: <?php echo e($customer['customer_id']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Customers / Edit</div>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Customers</a>
    </div>
</div>

<div class="page-content">
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <?php echo e($error_message); ?>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 650px;">
        <div class="card-header">
            <span>Customer Details &bull; <?php echo e($customer['customer_id']); ?></span>
        </div>
        <div class="card-body">
            <form action="edit.php?id=<?php echo e($id); ?>" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label>Customer ID</label>
                            <input type="text" class="form-control" value="<?php echo e($customer['customer_id']); ?>" disabled style="background:#e9ecef;">
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="status">Status <span style="color:#c9302c;">*</span></label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="Active" <?php echo (($_POST['status'] ?? $customer['status']) === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo (($_POST['status'] ?? $customer['status']) === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="customer_name">Customer Full Name <span style="color:#c9302c;">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" 
                           value="<?php echo e($_POST['customer_name'] ?? $customer['customer_name']); ?>" required>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="phone">Phone Number <span style="color:#c9302c;">*</span></label>
                            <input type="text" id="phone" name="phone" class="form-control" 
                                   value="<?php echo e($_POST['phone'] ?? $customer['phone']); ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo e($_POST['email'] ?? $customer['email']); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Postal Address</label>
                    <textarea id="address" name="address" class="form-control" rows="2"><?php echo e($_POST['address'] ?? $customer['address']); ?></textarea>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Update Customer</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
