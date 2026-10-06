<?php
/**
 * Distributor Dashboard Landing
 * Smart Business Management System - Phase 4 Common Layout
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

// Strict Role Check - Only Distributor allowed
require_role('Distributor');

$current_user = get_logged_in_user();
$page_title = 'Distributor Dashboard';
$active_menu = 'dashboard';
$base_url = '../';

include '../includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <h1>Distributor Dashboard</h1>
    <div class="page-breadcrumb">Smart Business / Distributor / Dashboard</div>
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
                            <td><strong style="color: #b7791f;"><?php echo htmlspecialchars($current_user['role_name']); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Database Record ID</th>
                            <td>#<?php echo htmlspecialchars($current_user['id']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Role Scope Overview Card -->
        <div class="grid-col-6">
            <div class="card">
                <div class="card-header">
                    <span>Distributor Role Scope</span>
                    <span class="badge badge-info">Phase 4 Ready</span>
                </div>
                <div class="card-body">
                    <table class="data-table">
                        <tr>
                            <th style="width: 160px;">Primary Operations</th>
                            <td>Stock Inward, Retailer Transfers, Wholesale Distribution</td>
                        </tr>
                        <tr>
                            <th>Layout Template</th>
                            <td>Shared Common Layout (Role Filtered)</td>
                        </tr>
                        <tr>
                            <th>Role Protection</th>
                            <td>Enforced (Distributor Clearance Verified)</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
include '../includes/footer.php';
?>
