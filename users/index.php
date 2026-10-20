<?php
/**
 * User Management - User List
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Strict Role Check - Only Admin allowed
require_role('Admin');

$current_user = get_logged_in_user();
$page_title = 'User Management';
$active_menu = 'users';

// Search and Filter inputs
$search = trim($_GET['search'] ?? '');
$role_filter = trim($_GET['role'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

// Build query
$where_clauses = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_clauses[] = "(u.user_id LIKE ? OR u.full_name LIKE ? OR u.email LIKE ? OR u.google_email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

if (!empty($role_filter)) {
    $where_clauses[] = "u.role_id = ?";
    $params[] = (int)$role_filter;
    $types .= 'i';
}

if (!empty($status_filter)) {
    $where_clauses[] = "u.status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT u.id, u.user_id, u.full_name, u.email, u.role_id, u.status, u.is_verified, u.google_id, u.created_at, r.role_name 
          FROM users u 
          JOIN roles r ON u.role_id = r.id 
          {$where_sql} 
          ORDER BY u.id ASC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Fetch all roles for the filter dropdown
$roles_res = mysqli_query($conn, "SELECT id, role_name FROM roles ORDER BY id ASC");
$roles_list = [];
while ($r = mysqli_fetch_assoc($roles_res)) {
    $roles_list[] = $r;
}

$flash_messages = get_flash();

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1>User Management</h1>
        <div class="page-breadcrumb">Smart Business / Admin / Users</div>
    </div>
    <div>
        <a href="create.php" class="btn btn-primary">+ Create New User</a>
    </div>
</div>

<div class="page-content">
    <?php foreach ($flash_messages as $flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endforeach; ?>

    <!-- Search & Filter Bar -->
    <div class="filter-bar">
        <form method="GET" action="index.php" class="filter-form">
            <input type="text" name="search" placeholder="Search by name, ID or email..." value="<?php echo e($search); ?>" style="min-width: 240px;">
            
            <select name="role">
                <option value="">-- All Roles --</option>
                <?php foreach ($roles_list as $r): ?>
                    <option value="<?php echo e($r['id']); ?>" <?php echo ((string)$role_filter === (string)$r['id']) ? 'selected' : ''; ?>>
                        <?php echo e($r['role_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value="">-- All Statuses --</option>
                <option value="Active" <?php echo ($status_filter === 'Active') ? 'selected' : ''; ?>>Active</option>
                <option value="Inactive" <?php echo ($status_filter === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
            </select>

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($search) || !empty($role_filter) || !empty($status_filter)): ?>
                <a href="index.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Showing <strong><?php echo mysqli_num_rows($result); ?></strong> record(s)
        </div>
    </div>

    <!-- Users Table Card -->
    <div class="card">
        <div class="card-header">
            <span>System Users</span>
            <span class="badge badge-info">Role-Based Access Control</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (mysqli_num_rows($result) === 0): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    <p style="font-size: 15px; margin-bottom: 8px;">No user records found matching the criteria.</p>
                    <a href="create.php" class="btn btn-primary btn-sm">Add First User</a>
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 80px;">User ID</th>
                            <th>Full Name</th>
                            <th>Email Address</th>
                            <th style="width: 110px;">Role</th>
                            <th style="width: 90px;">Verified</th>
                            <th style="width: 90px;">Status</th>
                            <th style="width: 140px;">Registered</th>
                            <th style="width: 220px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><strong><?php echo e($row['user_id']); ?></strong></td>
                                <td>
                                    <a href="view.php?id=<?php echo e($row['id']); ?>" style="color: #2b5797; font-weight: bold; text-decoration: none;">
                                        <?php echo e($row['full_name']); ?>
                                    </a>
                                    <?php if (!empty($row['google_id'])): ?>
                                        <span title="Linked with Google" style="font-size: 11px; color: #4285F4; margin-left: 4px;">&#9733; Google</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo e($row['email'] ?? '—'); ?></td>
                                <td>
                                    <span class="badge-role badge-<?php echo strtolower($row['role_name']); ?>">
                                        <?php echo e($row['role_name']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ((int)$row['is_verified'] === 1): ?>
                                        <span class="badge badge-success">&check; Verified</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'Active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></td>
                                <td style="text-align: center;">
                                    <div class="action-links">
                                        <a href="view.php?id=<?php echo e($row['id']); ?>" class="btn-info" title="View details">View</a>
                                        <a href="edit.php?id=<?php echo e($row['id']); ?>" class="btn-secondary" title="Edit user">Edit</a>
                                        
                                        <!-- Toggle Status Form -->
                                        <form method="POST" action="toggle_status.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to change this user\'s status?');">
                                            <?php echo csrf_input(); ?>
                                            <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">
                                            <button type="submit" class="<?php echo ($row['status'] === 'Active') ? 'btn-warning' : 'btn-success'; ?>" style="font-size: 11px;">
                                                <?php echo ($row['status'] === 'Active') ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>

                                        <!-- Safe Delete Form -->
                                        <?php if ((int)$row['id'] !== (int)$current_user['id']): ?>
                                            <form method="POST" action="delete.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to permanently delete this user? This cannot be undone.');">
                                                <?php echo csrf_input(); ?>
                                                <input type="hidden" name="id" value="<?php echo e($row['id']); ?>">
                                                <button type="submit" class="btn-danger" style="font-size: 11px;">Delete</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if ($stmt) {
    mysqli_stmt_close($stmt);
}
include '../includes/footer.php';
?>
