<?php
/**
 * Category Management - Edit Category
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role('Admin');

$current_user = get_logged_in_user();
$page_title = 'Edit Category';
$active_menu = 'categories';

$error_message = '';
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    set_flash('danger', "Invalid category specified.");
    header("Location: index.php");
    exit;
}

// Fetch category
$stmt = mysqli_prepare($conn, "SELECT * FROM categories WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$category = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$category) {
    set_flash('danger', "Category not found.");
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $category_name = trim($_POST['category_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['Active', 'Inactive']) ? $_POST['status'] : 'Active';

        if (empty($category_name)) {
            $error_message = "Category name is required.";
        } else {
            // Check duplicate name excluding current category
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM categories WHERE LOWER(category_name) = LOWER(?) AND id != ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, "si", $category_name, $id);
            mysqli_stmt_execute($check_stmt);
            $check_res = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_res) > 0) {
                $error_message = "Another category already exists with that name.";
                mysqli_stmt_close($check_stmt);
            } else {
                mysqli_stmt_close($check_stmt);

                $update = mysqli_prepare($conn, "UPDATE categories SET category_name = ?, description = ?, status = ? WHERE id = ?");
                mysqli_stmt_bind_param($update, "sssi", $category_name, $description, $status, $id);
                if (mysqli_stmt_execute($update)) {
                    mysqli_stmt_close($update);
                    set_flash('success', "Category '{$category_name}' updated successfully.");
                    header("Location: index.php");
                    exit;
                } else {
                    $error_message = "Failed to update category: " . mysqli_error($conn);
                    mysqli_stmt_close($update);
                }
            }
        }
    }
}

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Edit Category: <?php echo e($category['category_name']); ?></h1>
        <div class="page-breadcrumb">Smart Business / Admin / Categories / Edit</div>
    </div>
    <div>
        <a href="index.php" class="btn btn-secondary btn-sm">&larr; Back to Categories</a>
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
            <span>Edit Category Details</span>
        </div>
        <div class="card-body">
            <form action="edit.php?id=<?php echo e($id); ?>" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="form-group">
                    <label for="category_name">Category Name <span style="color:#c9302c;">*</span></label>
                    <input type="text" id="category_name" name="category_name" class="form-control" 
                           value="<?php echo e($_POST['category_name'] ?? $category['category_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea id="description" name="description" class="form-control" rows="3"><?php echo e($_POST['description'] ?? $category['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Status <span style="color:#c9302c;">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="Active" <?php echo (($_POST['status'] ?? $category['status']) === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo (($_POST['status'] ?? $category['status']) === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Update Category</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
