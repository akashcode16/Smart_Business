<?php
/**
 * Supplier Management - Add Supplier
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();
$page_title = 'Add Supplier';
$active_menu = 'suppliers';

$error_message = '';

// Generate supplier ID
$id_res = mysqli_query($conn, "SELECT supplier_id FROM suppliers WHERE supplier_id LIKE 'SUP%' ORDER BY id DESC LIMIT 1");
$next_num = 1;
if ($id_res && $row = mysqli_fetch_assoc($id_res)) {
    $next_num = (int)substr($row['supplier_id'], 3) + 1;
}
$suggested_id = sprintf("SUP%03d", $next_num);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $supplier_id = strtoupper(trim($_POST['supplier_id'] ?? ''));
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $address = trim($_POST['address'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

        if (empty($supplier_id) || empty($supplier_name) || empty($phone)) {
            $error_message = "Supplier ID, Contact Name, and Phone number are required.";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Check uniqueness of supplier_id
            $chk = mysqli_prepare($conn, "SELECT id FROM suppliers WHERE supplier_id = ? LIMIT 1");
            mysqli_stmt_bind_param($chk, "s", $supplier_id);
            mysqli_stmt_execute($chk);
            $res = mysqli_stmt_get_result($chk);

            if (mysqli_num_rows($res) > 0) {
                $error_message = "A supplier with ID '{$supplier_id}' already exists.";
                mysqli_stmt_close($chk);
            } else {
                mysqli_stmt_close($chk);

                $insert = mysqli_prepare($conn, "INSERT INTO suppliers (supplier_id, supplier_name, company_name, phone, email, address, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($insert, "sssssss", $supplier_id, $supplier_name, $company_name, $phone, $email, $address, $status);
                
                if (mysqli_stmt_execute($insert)) {
                    mysqli_stmt_close($insert);
                    set_flash('success', "Supplier '{$supplier_name}' ({$supplier_id}) added successfully.");
                    header("Location: index.php");
                    exit;
                } else {
                    $error_message = "Failed to add supplier: " . mysqli_error($conn);
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
        <h1>Add New Supplier</h1>
        <div class="page-breadcrumb">Smart Business / Suppliers / Add</div>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Suppliers</a>
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
            <span>Supplier Profile Details</span>
        </div>
        <div class="card-body">
            <form action="create.php" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="supplier_id">Supplier ID <span style="color:#c9302c;">*</span></label>
                            <input type="text" id="supplier_id" name="supplier_id" class="form-control" 
                                   value="<?php echo isset($_POST['supplier_id']) ? e($_POST['supplier_id']) : e($suggested_id); ?>" required>
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
                    <label for="supplier_name">Contact Person / Supplier Name <span style="color:#c9302c;">*</span></label>
                    <input type="text" id="supplier_name" name="supplier_name" class="form-control" 
                           placeholder="e.g. Ramesh Kumar" 
                           value="<?php echo isset($_POST['supplier_name']) ? e($_POST['supplier_name']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="company_name">Company / Business Name</label>
                    <input type="text" id="company_name" name="company_name" class="form-control" 
                           placeholder="e.g. Apex Industrial Supplies Pvt Ltd" 
                           value="<?php echo isset($_POST['company_name']) ? e($_POST['company_name']) : ''; ?>">
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="phone">Phone Number <span style="color:#c9302c;">*</span></label>
                            <input type="text" id="phone" name="phone" class="form-control" 
                                   placeholder="e.g. +91 9876543210" 
                                   value="<?php echo isset($_POST['phone']) ? e($_POST['phone']) : ''; ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   placeholder="e.g. vendor@company.com" 
                                   value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Postal Address</label>
                    <textarea id="address" name="address" class="form-control" rows="2" 
                              placeholder="Street, City, State, PIN..."><?php echo isset($_POST['address']) ? e($_POST['address']) : ''; ?></textarea>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Save Supplier</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
