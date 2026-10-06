<?php
/**
 * Admin Dashboard Landing
 * Smart Business Management System - Phase 4 Common Layout
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Strict Role Check - Only Admin allowed
require_role('Admin');

$current_user = get_logged_in_user();
$page_title = 'Admin Dashboard';
$active_menu = 'dashboard';
$base_url = '../';

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1>Admin Dashboard</h1>
    <div class="page-breadcrumb">Smart Business / Admin / Dashboard</div>
</div>

<!-- Page Content -->
<div class="page-content">
    <div class="grid-row">
        <!-- Session / Account Status Card -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Authorized Session Details</span>
                    <span class="badge badge-success">Active &amp; Authorized</span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tr>
                            <th style="width: 160px;">User ID</th>
                            <td><?php echo htmlspecialchars($current_user['user_id']); ?></td>
                        </tr>
                        <tr>
                            <th>Full Name</th>
                            <td><?php echo htmlspecialchars($current_user['full_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Assigned Role</th>
                            <td><strong style="color: #2b5797;"><?php echo htmlspecialchars($current_user['role_name']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Database Record ID</th>
                            <td>#<?php echo htmlspecialchars($current_user['id']); ?></td>
                        </tr>
                        <tr>
                            <th>Authentication Type</th>
                            <td>Google OAuth / Session Auth</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- System Status & Architecture Overview Card -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>System Status</span>
                    <span class="badge badge-info">Phase 4 Complete</span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tr>
                            <th style="width: 160px;">Layout Template</th>
                            <td>Standard Enterprise Layout (Reusable)</td>
                        </tr>
                        <tr>
                            <th>Header / Sidebar</th>
                            <td>Active (Role-Based Menu Filtering)</td>
                        </tr>
                        <tr>
                            <th>Role Protection</th>
                            <td>Enforced (Admin Clearance Verified)</td>
                        </tr>
                        <tr>
                            <th>Database Connection</th>
                            <td>Connected (smart_business)</td>
                        </tr>
                        <tr>
                            <th>Next Step</th>
                            <td>Ready for Phase 5 Module Implementation</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Administrative Modules Overview Card -->
    <div class="card">
        <div class="card-header">
            <span>Prepared Administrative Modules</span>
            <span style="font-size: 12px; font-weight: normal; color: #666;">Navigation prepared in sidebar</span>
        </div>
        <div class="card-body">
            <p style="color: #555; margin-bottom: 12px;">
                The navigation bar on the left is configured for the following administrative management modules:
            </p>

            <div class="module-list-grid">
                <div class="module-card-item">
                    <h4>User Management</h4>
                    <p>Manage system users, credentials and role assignments.</p>
                </div>
                <div class="module-card-item">
                    <h4>Categories</h4>
                    <p>Manage product classifications and categories.</p>
                </div>
                <div class="module-card-item">
                    <h4>Products</h4>
                    <p>Product catalog, pricing, brands and unit definitions.</p>
                </div>
                <div class="module-card-item">
                    <h4>Suppliers</h4>
                    <p>Vendor directory, contact details and supply profiles.</p>
                </div>
                <div class="module-card-item">
                    <h4>Customers</h4>
                    <p>Customer accounts, contact directory and records.</p>
                </div>
                <div class="module-card-item">
                    <h4>Stock</h4>
                    <p>Inventory level monitoring across distribution channels.</p>
                </div>
                <div class="module-card-item">
                    <h4>Purchases</h4>
                    <p>Supplier purchase orders and incoming inventory.</p>
                </div>
                <div class="module-card-item">
                    <h4>Stock Transfers</h4>
                    <p>Stockiest &rarr; Distributor &rarr; Retailer transfers.</p>
                </div>
                <div class="module-card-item">
                    <h4>Sales</h4>
                    <p>Sales transactions, invoices and customer billing.</p>
                </div>
                <div class="module-card-item">
                    <h4>Reports</h4>
                    <p>Financial, sales, purchase and inventory reporting.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
