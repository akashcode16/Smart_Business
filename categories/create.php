<?php
/**
 * Category Management - Create Category
 * Smart Business Management System
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role('Admin');

$current_user = get_logged_in_user();
$page_title = 'Add Category';
$active_menu = 'categories';

$error_message = '';

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
            // Check for duplicate category name
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM categories WHERE LOWER(category_name) = LOWER(?) LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, "s", $category_name);
            mysqli_stmt_execute($check_stmt);
            $check_res = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_res) > 0) {
                $error_message = "A category with this name already exists.";
                mysqli_stmt_close($check_stmt);
            } else {
                mysqli_stmt_close($check_stmt);

                $insert = mysqli_prepare($conn, "INSERT INTO categories (category_name, description, status) VALUES (?, ?, ?)");
                mysqli_stmt_bind_param($insert, "sss", $category_name, $description, $status);
                if (mysqli_stmt_execute($insert)) {
                    mysqli_stmt_close($insert);
                    set_flash('success', "Category '{$category_name}' created successfully.");
                    header("Location: index.php");
                    exit;
                } else {
                    $error_message = "Failed to insert category: " . mysqli_error($conn);
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
        <h1>Add New Category</h1>
        <div class="page-breadcrumb">Smart Business / Admin / Categories / Create</div>
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
            <span>Category Information</span>
        </div>
        <div class="card-body">
            <form action="create.php" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="form-group">
                    <label for="category_name">Category Name <span style="color:#c9302c;">*</span></label>
                    <input type="text" id="category_name" name="category_name" class="form-control" 
                           placeholder="e.g. Electronics / FMCG / Beverages" 
                           value="<?php echo isset($_POST['category_name']) ? e($_POST['category_name']) : ''; ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label for="description">Description (Optional)</label>
                    <textarea id="description" name="description" class="form-control" rows="3" 
                              placeholder="Brief description of the product group..."><?php echo isset($_POST['description']) ? e($_POST['description']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Status <span style="color:#c9302c;">*</span></label>
                    <select id="status" name="status" class="form-control" required>
                        <option value="Active" <?php echo (($_POST['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo (($_POST['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div style="margin-top: 25px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">Save Category</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
