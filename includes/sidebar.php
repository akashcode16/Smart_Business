<?php
/**
 * Common Sidebar Navigation
 * Smart Business Management System
 */

if (!isset($current_user) || empty($current_user)) {
    $current_user = get_logged_in_user();
}
$user_role = strtolower($current_user['role_name'] ?? '');
$base = defined('BASE_URL') ? BASE_URL : '/smart_business/';
$active = isset($active_menu) ? $active_menu : '';
?>
<aside class="app-sidebar">
    <div class="sidebar-heading">
        <span>NAVIGATION MENU</span>
    </div>

    <ul class="sidebar-menu">
        <?php if ($user_role === 'admin'): ?>
            <!-- Admin Navigation (Phase 4 Layout Spec) -->
            <li class="<?php echo ($active === 'dashboard') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>admin/index.php">Dashboard</a>
            </li>
            <li class="<?php echo ($active === 'users') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>users/index.php">User Management</a>
            </li>
            <li class="<?php echo ($active === 'categories') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>categories/index.php">Categories</a>
            </li>
            <li class="<?php echo ($active === 'products') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>products/index.php">Products</a>
            </li>
            <li class="<?php echo ($active === 'suppliers') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>suppliers/index.php">Suppliers</a>
            </li>
            <li class="<?php echo ($active === 'customers') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>customers/index.php">Customers</a>
            </li>
            <li class="<?php echo ($active === 'stock') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>stock/index.php">Stock</a>
            </li>
            <li class="<?php echo ($active === 'purchases') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>purchases/index.php">Purchases</a>
            </li>
            <li class="<?php echo ($active === 'transfers') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>stock_transfers/index.php">Stock Transfers</a>
            </li>
            <li class="<?php echo ($active === 'sales') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>sales/index.php">Sales</a>
            </li>
            <li class="<?php echo ($active === 'reports') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>reports/index.php">Reports</a>
            </li>

        <?php elseif ($user_role === 'stockiest'): ?>
            <!-- Stockiest Navigation -->
            <li class="<?php echo ($active === 'dashboard') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>stockiest/index.php">Dashboard</a>
            </li>
            <li class="<?php echo ($active === 'products') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>products/index.php">Products</a>
            </li>
            <li class="<?php echo ($active === 'suppliers') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>suppliers/index.php">Suppliers</a>
            </li>
            <li class="<?php echo ($active === 'purchases') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>purchases/index.php">Purchases</a>
            </li>
            <li class="<?php echo ($active === 'stock') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>stock/index.php">Stock</a>
            </li>
            <li class="<?php echo ($active === 'transfers') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>stock_transfers/index.php">Stock Transfers</a>
            </li>
            <li class="<?php echo ($active === 'reports') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>reports/index.php">Reports</a>
            </li>

        <?php elseif ($user_role === 'distributor'): ?>
            <!-- Distributor Navigation -->
            <li class="<?php echo ($active === 'dashboard') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>distributor/index.php">Dashboard</a>
            </li>
            <li class="<?php echo ($active === 'stock') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>stock/index.php">Stock</a>
            </li>
            <li class="<?php echo ($active === 'transfers') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>stock_transfers/index.php">Stock Transfers</a>
            </li>
            <li class="<?php echo ($active === 'sales') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>sales/index.php">Sales</a>
            </li>
            <li class="<?php echo ($active === 'customers') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>customers/index.php">Customers</a>
            </li>
            <li class="<?php echo ($active === 'reports') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>reports/index.php">Reports</a>
            </li>

        <?php elseif ($user_role === 'retailer'): ?>
            <!-- Retailer Navigation -->
            <li class="<?php echo ($active === 'dashboard') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>retailer/index.php">Dashboard</a>
            </li>
            <li class="<?php echo ($active === 'stock') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>stock/index.php">Stock</a>
            </li>
            <li class="<?php echo ($active === 'sales') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>sales/index.php">Sales</a>
            </li>
            <li class="<?php echo ($active === 'customers') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>customers/index.php">Customers</a>
            </li>
            <li class="<?php echo ($active === 'reports') ? 'active' : ''; ?>">
                <a href="<?php echo $base; ?>reports/index.php">Reports</a>
            </li>
        <?php endif; ?>

        <li class="sidebar-divider"></li>

        <li>
            <a href="<?php echo $base; ?>logout.php" class="menu-logout">Logout</a>
        </li>
    </ul>

    <div class="sidebar-footer-info">
        <div class="sidebar-user-name"><?php echo htmlspecialchars($current_user['full_name'] ?? ''); ?></div>
        <div class="sidebar-user-role"><?php echo htmlspecialchars($current_user['role_name'] ?? ''); ?> Panel</div>
    </div>
</aside>
