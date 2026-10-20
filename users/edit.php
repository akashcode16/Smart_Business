<?php
/**
 * User Management - Edit User
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role('Admin');

$current_user = get_logged_in_user();
$page_title = 'Edit User';
$active_menu = 'users';

$error_message = '';
$user_id_param = (int)($_GET['id'] ?? 0);

if ($user_id_param <= 0) {
    set_flash('danger', "Invalid user specified.");
    header("Location: index.php");
    exit;
}

// Fetch user data
$query = "SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $user_id_param);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$user_data = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$user_data) {
    set_flash('danger', "User not found in system.");
    header("Location: index.php");
    exit;
}

// Fetch roles
$roles_res = mysqli_query($conn, "SELECT id, role_name FROM roles ORDER BY id ASC");
$roles_list = [];
while ($r = mysqli_fetch_assoc($roles_res)) {
    $roles_list[] = $r;
}

// Check count of active admins
$count_admins_res = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role_id = (SELECT id FROM roles WHERE role_name = 'Admin') AND status = 'Active'");
$admin_count = (int)(mysqli_fetch_assoc($count_admins_res)['total'] ?? 0);
$is_only_active_admin = ($admin_count <= 1 && $user_data['role_name'] === 'Admin' && $user_data['status'] === 'Active');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role_id = (int)($_POST['role_id'] ?? 0);
        $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';
        $is_verified = isset($_POST['is_verified']) ? 1 : 0;
        $new_password = $_POST['new_password'] ?? '';

        if (empty($full_name) || empty($email) || empty($role_id)) {
            $error_message = "Full Name, Email and Role are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } elseif (!empty($new_password) && strlen($new_password) < 6) {
            $error_message = "New password must be at least 6 characters long.";
        } else {
            // Check if attempting to demote or deactivate the last active admin
            $admin_role_id_res = mysqli_query($conn, "SELECT id FROM roles WHERE role_name = 'Admin' LIMIT 1");
            $admin_role_id = (int)(mysqli_fetch_assoc($admin_role_id_res)['id'] ?? 1);

            if ($is_only_active_admin && ($role_id !== $admin_role_id || $status !== 'Active')) {
                $error_message = "Safety Check: You cannot demote or deactivate the last remaining active Administrator.";
            } else {
                // Check email uniqueness excluding this user
                $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE (LOWER(email) = ? OR LOWER(google_email) = ?) AND id != ? LIMIT 1");
                mysqli_stmt_bind_param($check_stmt, "ssi", $email, $email, $user_id_param);
                mysqli_stmt_execute($check_stmt);
                $check_res = mysqli_stmt_get_result($check_stmt);

                if (mysqli_num_rows($check_res) > 0) {
                    $error_message = "This email is already registered to another account.";
                    mysqli_stmt_close($check_stmt);
                } else {
                    mysqli_stmt_close($check_stmt);

                    // Update query
                    if (!empty($new_password)) {
                        $hash = password_hash($new_password, PASSWORD_BCRYPT);
                        $update_sql = "UPDATE users SET full_name = ?, email = ?, role_id = ?, status = ?, is_verified = ?, password = ? WHERE id = ?";
                        $up_stmt = mysqli_prepare($conn, $update_sql);
                        mysqli_stmt_bind_param($up_stmt, "ssisisi", $full_name, $email, $role_id, $status, $is_verified, $hash, $user_id_param);
                    } else {
                        $update_sql = "UPDATE users SET full_name = ?, email = ?, role_id = ?, status = ?, is_verified = ? WHERE id = ?";
                        $up_stmt = mysqli_prepare($conn, $update_sql);
                        mysqli_stmt_bind_param($up_stmt, "ssisii", $full_name, $email, $role_id, $status, $is_verified, $user_id_param);
                    }

                    if (mysqli_stmt_execute($up_stmt)) {
                        mysqli_stmt_close($up_stmt);
                        set_flash('success', "User {$user_data['user_id']} ({$full_name}) updated successfully.");
                        header("Location: index.php");
                        exit;
                    } else {
                        $error_message = "Failed to update user: " . mysqli_error($conn);
                        mysqli_stmt_close($up_stmt);
                    }
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Edit User Account: <?php echo e($user_data['user_id']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Admin / Users / Edit</div>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Users</a>
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
            <span>Edit Details &bull; <?php echo e($user_data['user_id']); ?></span>
            <span class="badge-role badge-<?php echo strtolower($user_data['role_name']); ?>"><?php echo e($user_data['role_name']); ?></span>
        </div>
        <div class="card-body">
            <form action="edit.php?id=<?php echo e($user_id_param); ?>" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="form-group">
                    <label for="user_id_display">User ID (System Assigned)</label>
                    <input type="text" id="user_id_display" class="form-control" value="<?php echo e($user_data['user_id']); ?>" disabled style="background:#e9ecef;">
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name <span style="color:#c9302c;">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           value="<?php echo e($_POST['full_name'] ?? $user_data['full_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span style="color:#c9302c;">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" 
                           value="<?php echo e($_POST['email'] ?? $user_data['email']); ?>" required>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="role_id">Assigned Role <span style="color:#c9302c;">*</span></label>
                            <select id="role_id" name="role_id" class="form-control" required>
                                <?php foreach ($roles_list as $r): ?>
                                    <option value="<?php echo e($r['id']); ?>" <?php echo ((string)($_POST['role_id'] ?? $user_data['role_id']) === (string)$r['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($r['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($is_only_active_admin): ?>
                                <small style="color: #b7791f; font-size: 11px;">Note: Last active administrator account.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="status">Account Status <span style="color:#c9302c;">*</span></label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="Active" <?php echo (($_POST['status'] ?? $user_data['status']) === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo (($_POST['status'] ?? $user_data['status']) === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="new_password">Reset Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" 
                           placeholder="Leave blank to retain current password">
                    <small style="color: #666; font-size: 11px;">Only enter text if you wish to reset the user's password.</small>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_verified" name="is_verified" value="1" 
                               <?php echo ((int)($_POST['is_verified'] ?? $user_data['is_verified']) === 1) ? 'checked' : ''; ?>>
                        <label for="is_verified" style="font-weight: normal; margin-bottom: 0; cursor: pointer;">
                            Account email is verified
                        </label>
                    </div>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
