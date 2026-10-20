<?php
/**
 * Supplier Management - Edit Supplier
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest']);

$current_user = get_logged_in_user();
$page_title = 'Edit Supplier';
$active_menu = 'suppliers';

$error_message = '';
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', "Invalid supplier specified.");
    header("Location: index.php");
    exit;
}

$stmt = mysqli_prepare($conn, "SELECT * FROM suppliers WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$supplier = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$supplier) {
    set_flash('danger', "Supplier not found.");
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $supplier_name = trim($_POST['supplier_name'] ?? '');
        $company_name = trim($_POST['company_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $address = trim($_POST['address'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

        if (empty($supplier_name) || empty($phone)) {
            $error_message = "Contact Name and Phone number are required.";
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            $update = mysqli_prepare($conn, "UPDATE suppliers SET supplier_name = ?, company_name = ?, phone = ?, email = ?, address = ?, status = ? WHERE id = ?");
            mysqli_stmt_bind_param($update, "ssssssi", $supplier_name, $company_name, $phone, $email, $address, $status, $id);
            if (mysqli_stmt_execute($update)) {
                mysqli_stmt_close($update);
                set_flash('success', "Supplier '{$supplier_name}' updated successfully.");
                header("Location: index.php");
                exit;
            } else {
                $error_message = "Failed to update supplier: " . mysqli_error($conn);
                mysqli_stmt_close($update);
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Edit Supplier: <?php echo e($supplier['supplier_id']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Suppliers / Edit</div>
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
            <span>Edit Profile &bull; <?php echo e($supplier['supplier_id']); ?></span>
        </div>
        <div class="card-body">
            <form action="edit.php?id=<?php echo e($id); ?>" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label>Supplier ID</label>
                            <input type="text" class="form-control" value="<?php echo e($supplier['supplier_id']); ?>" disabled style="background:#e9ecef;">
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="status">Status <span style="color:#c9302c;">*</span></label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="Active" <?php echo (($_POST['status'] ?? $supplier['status']) === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo (($_POST['status'] ?? $supplier['status']) === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="supplier_name">Contact Person / Supplier Name <span style="color:#c9302c;">*</span></label>
                    <input type="text" id="supplier_name" name="supplier_name" class="form-control" 
                           value="<?php echo e($_POST['supplier_name'] ?? $supplier['supplier_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="company_name">Company / Business Name</label>
                    <input type="text" id="company_name" name="company_name" class="form-control" 
                           value="<?php echo e($_POST['company_name'] ?? $supplier['company_name']); ?>">
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="phone">Phone Number <span style="color:#c9302c;">*</span></label>
                            <input type="text" id="phone" name="phone" class="form-control" 
                                   value="<?php echo e($_POST['phone'] ?? $supplier['phone']); ?>" required>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo e($_POST['email'] ?? $supplier['email']); ?>">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Postal Address</label>
                    <textarea id="address" name="address" class="form-control" rows="2"><?php echo e($_POST['address'] ?? $supplier['address']); ?></textarea>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Update Supplier</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
