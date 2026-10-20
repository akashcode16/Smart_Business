<?php
/**
 * User Management - Create User
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role('Admin');

$current_user = get_logged_in_user();
$page_title = 'Create User';
$active_menu = 'users';

$error_message = '';

// Fetch roles for select dropdown
$roles_res = mysqli_query($conn, "SELECT id, role_name FROM roles ORDER BY id ASC");
$roles_list = [];
while ($r = mysqli_fetch_assoc($roles_res)) {
    $roles_list[] = $r;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $role_id = (int)($_POST['role_id'] ?? 0);
        $password = $_POST['password'] ?? '';
        $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';
        $is_verified = isset($_POST['is_verified']) ? 1 : 0;

        if (empty($full_name) || empty($email) || empty($role_id) || empty($password)) {
            $error_message = "All required fields must be completed.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } elseif (strlen($password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } else {
            // Check email uniqueness
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE LOWER(email) = ? OR LOWER(google_email) = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, "ss", $email, $email);
            mysqli_stmt_execute($check_stmt);
            $check_res = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_res) > 0) {
                $error_message = "This email address is already in use by another account.";
                mysqli_stmt_close($check_stmt);
            } else {
                mysqli_stmt_close($check_stmt);

                // Determine prefix from role
                $role_name = 'User';
                foreach ($roles_list as $r) {
                    if ((int)$r['id'] === $role_id) {
                        $role_name = $r['role_name'];
                        break;
                    }
                }

                $prefix_map = [
                    'Admin' => 'ADM',
                    'Stockiest' => 'STK',
                    'Distributor' => 'DST',
                    'Retailer' => 'RET'
                ];
                $prefix = $prefix_map[$role_name] ?? 'USR';

                // Find next user_id
                $id_query = "SELECT user_id FROM users WHERE user_id LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1";
                $id_res = mysqli_query($conn, $id_query);
                $next_num = 1;
                if ($id_res && $row = mysqli_fetch_assoc($id_res)) {
                    $num_part = (int)substr($row['user_id'], strlen($prefix));
                    $next_num = $num_part + 1;
                }
                $user_id = sprintf("%s%03d", $prefix, $next_num);

                // Hash password
                $password_hash = password_hash($password, PASSWORD_BCRYPT);

                $insert_query = "INSERT INTO users (user_id, full_name, email, password, role_id, status, is_verified) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                if ($stmt = mysqli_prepare($conn, $insert_query)) {
                    mysqli_stmt_bind_param($stmt, "ssssisi", $user_id, $full_name, $email, $password_hash, $role_id, $status, $is_verified);
                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);
                        set_flash('success', "User account {$user_id} ({$full_name}) created successfully.");
                        header("Location: index.php");
                        exit;
                    } else {
                        $error_message = "Failed to insert user into database: " . mysqli_error($conn);
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    $error_message = "Database prepare error: " . mysqli_error($conn);
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Create New User</h1>
        <div class="page-breadcrumb">Smart Business / Admin / Users / Create</div>
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
            <span>New User Details</span>
            <span style="font-size: 12px; color: #666;">User ID will be auto-generated</span>
        </div>
        <div class="card-body">
            <form action="create.php" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="form-group">
                    <label for="full_name">Full Name <span style="color:#c9302c;">*</span></label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           placeholder="e.g. John Doe / Apex Retail" 
                           value="<?php echo isset($_POST['full_name']) ? e($_POST['full_name']) : ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span style="color:#c9302c;">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="e.g. user@business.com" 
                           value="<?php echo isset($_POST['email']) ? e($_POST['email']) : ''; ?>" required>
                </div>

                <div class="grid-row" style="margin-bottom: 0;">
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="role_id">Assigned Role <span style="color:#c9302c;">*</span></label>
                            <select id="role_id" name="role_id" class="form-control" required>
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles_list as $r): ?>
                                    <option value="<?php echo e($r['id']); ?>" <?php echo ((string)($_POST['role_id'] ?? '') === (string)$r['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($r['role_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="grid-col-6">
                        <div class="form-group">
                            <label for="status">Account Status <span style="color:#c9302c;">*</span></label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="Active" <?php echo (($_POST['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo (($_POST['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password (Min 6 characters) <span style="color:#c9302c;">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter initial secure password" required>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="is_verified" name="is_verified" value="1" <?php echo (!isset($_POST['full_name']) || isset($_POST['is_verified'])) ? 'checked' : ''; ?>>
                        <label for="is_verified" style="font-weight: normal; margin-bottom: 0; cursor: pointer;">
                            Mark email as verified immediately (allows direct login without email token)
                        </label>
                    </div>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Create User Account</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
