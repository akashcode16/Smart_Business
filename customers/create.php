<?php
/**
 * Customer Management - Add Customer
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Distributor', 'Retailer']);

$current_user = get_logged_in_user();
$page_title = 'Add Customer';
$active_menu = 'customers';

$error_message = '';

// Generate next Customer ID
$id_res = mysqli_query($conn, "SELECT customer_id FROM customers WHERE customer_id LIKE 'CUST%' ORDER BY id DESC LIMIT 1");
$next_num = 1;
if ($id_res && $row = mysqli_fetch_assoc($id_res)) {
    $next_num = (int)substr($row['customer_id'], 4) + 1;
}
$suggested_id = sprintf("CUST%03d", $next_num);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $customer_id = strtoupper(trim($_POST['customer_id'] ?? ''));
        $customer_name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $address = trim($_POST['address'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

        if (empty($customer_id) || empty($customer_name) || empty($phone)) {
            $error_message = "Customer ID, Name, and Phone number are required.";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check unique customer_id
            $chk = mysqli_prepare($conn, "SELECT id FROM customers WHERE customer_id = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, "s", $customer_id);
            mysqli_stmt_execute($chk);
            $res = mysqli_stmt_get_result($chk);

            if (mysqli_num_rows($res) > 0) {
                $error_message = "A customer with ID '{$customer_id}' already exists.";
                mysqli_stmt_close($chk);
            } else {
                mysqli_stmt_close($chk);

                $created_by_id = (int)$current_user['id'];
                $insert = mysqli_prepare($conn, "INSERT INTO customers (customer_id, customer_name, phone, email, address, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($insert, "sssssis", $customer_id, $customer_name, $phone, $email, $address, $created_by_id, $status);
                
                if (mysqli_stmt_execute($insert)) {
                    mysqli_stmt_close($insert);
                    set_flash('success', "Customer '{$customer_name}' ({$customer_id}) registered successfully.");
                    header("Location: index.php");
                    exit;
                } else {
                    $error_message = "Failed to add customer: " . mysqli_error($conn);
                    mysqli_stmt_close($insert);
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Add New Customer</h1>
        <div class="page-breadcrumb">Smart Business / Customers / Add</div>
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
            <span>Customer Registration Form</span>
        </div>
        <div class="card-body">
            <form action="create.php" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="customer_id">Customer ID <span style="color:#c9302c;">*</span></label>
                            <input type="text" id="customer_id" name="customer_id" class="form-control" 
                                   value="<?php echo isset($_POST['customer_id']) ? e($_POST['customer_id']) : e($suggested_id); ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="status">Status <span style="color:#c9302c;">*</span></label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="Active" <?php echo (($_POST['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo (($_POST['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="customer_name">Customer Full Name <span style="color:#c9302c;">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" class="form-control" 
                           placeholder="e.g. Suresh Raina / Star Supermarket" 
                           value="<?php echo isset($_POST['customer_name']) ? e($_POST['customer_name']) : ''; ?>" required>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="phone">Phone Number <span style="color:#c9302c;">*</span></label>
                            <input type="text" id="phone" name="phone" class="form-control" 
                                   placeholder="e.g. +91 9841023456" 
                                   value="<?php echo isset($_POST['phone']) ? e($_POST['phone']) : ''; ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   placeholder="e.g. customer@gmail.com" 
                                   value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address / Delivery Location</label>
                    <textarea id="address" name="address" class="form-control" rows="2" 
                              placeholder="Door No, Street, Area, City..."><?php echo isset($_POST['address']) ? e($_POST['address']) : ''; ?></textarea>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Register Customer</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
