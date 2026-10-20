<?php
/**
 * Stock Transfers - Transfer List & History
 * Smart Business Management System
 * Distribution Workflow: Stockiest -> Distributor -> Retailer
 */

require_once '../config/database.php';
require_once '../includes/auth.php';

require_role(['Admin', 'Stockiest', 'Distributor']);

$current_user = get_logged_in_user();
$page_title = 'Stock Transfers';
$active_menu = 'transfers';

$search = trim($_GET['search'] ?? '');
$direction = trim($_GET['direction'] ?? ''); // 'outgoing', 'incoming', or all
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

$where_clauses = [];
$params = [];
$types = '';

// Role-based visibility
if (!is_admin()) {
    $uid = (int)$current_user['id'];
    if ($direction === 'outgoing') {
        $where_clauses[] = "st.from_user_id = ?";
        $params[] = $uid;
        $types .= 'i';
    } elseif ($direction === 'incoming') {
        $where_clauses[] = "st.to_user_id = ?";
        $params[] = $uid;
        $types .= 'i';
    } else {
        $where_clauses[] = "(st.from_user_id = ? OR st.to_user_id = ?)";
        $params[] = $uid;
        $params[] = $uid;
        $types .= 'ii';
    }
}

if (!empty($search)) {
    $where_clauses[] = "(st.transfer_id LIKE ? OR p.product_name LIKE ? OR u_from.full_name LIKE ? OR u_to.full_name LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'ssss';
}

if (!empty($from_date)) {
    $where_clauses[] = "st.transfer_date >= ?";
    $params[] = $from_date;
    $types .= 's';
}

if (!empty($to_date)) {
    $where_clauses[] = "st.transfer_date <= ?";
    $params[] = $to_date;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$query = "SELECT st.*, 
          p.product_id as sku, p.product_name, p.unit,
          u_from.full_name as from_name, u_from.user_id as from_code, r_from.role_name as from_role,
          u_to.full_name as to_name, u_to.user_id as to_code, r_to.role_name as to_role 
          FROM stock_transfers st 
          JOIN products p ON st.product_id = p.id 
          JOIN users u_from ON st.from_user_id = u_from.id 
          JOIN roles r_from ON u_from.role_id = r_from.id 
          JOIN users u_to ON st.to_user_id = u_to.id 
          JOIN roles r_to ON u_to.role_id = r_to.id 
          {$where_sql} 
          ORDER BY st.transfer_date DESC, st.id DESC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$flash_messages = get_flash();

// Can current user initiate transfers? Stockiest and Distributor and Admin can. (Retailer cannot transfer out).
$can_transfer = (is_admin() || is_stockiest() || is_distributor());

include '../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1>Stock Transfers</h1>
        <div class="page-breadcrumb">Smart Business / Distribution / Stock Transfers</div>
    </div>
    <?php if ($can_transfer): ?>
        <div>
            <a href="create.php" class="btn btn-primary">+ Transfer Stock</a>
        </div>
    <?php endif; ?>
</div>

<div class="page-content">
    <?php foreach ($flash_messages as $flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>">
            <?php echo e($flash['message']); ?>
        </div>
    <?php endforeach; ?>

    <!-- Workflow Banner -->
    <div style="background: #ffffff; border: 1px solid #dcdfe6; border-left: 4px solid #2b5797; padding: 12px 16px; border-radius: 3px; margin-bottom: 20px;">
        <span style="font-weight: bold; color: #1a365d;">Supply Chain Distribution Workflow:</span>
        <span style="margin-left: 10px; color: #555;">
            <strong style="color: #2e7d32;">Stockiest</strong> (Bulk Inward) &rarr; 
            <strong style="color: #b7791f;">Distributor</strong> (Wholesale Hub) &rarr; 
            <strong style="color: #6b46c1;">Retailer</strong> (POS Counter) &rarr; 
            <strong>Customer</strong>
        </span>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" action="index.php" class="filter-form">
            <input type="text" name="search" placeholder="Search Transfer #, product, party..." value="<?php echo e($search); ?>" style="min-width: 220px;">
            
            <?php if (!is_admin()): ?>
                <select name="direction">
                    <option value="">-- All (In &amp; Out) --</option>
                    <option value="outgoing" <?php echo ($direction === 'outgoing') ? 'selected' : ''; ?>>Outgoing (Sent)</option>
                    <option value="incoming" <?php echo ($direction === 'incoming') ? 'selected' : ''; ?>>Incoming (Received)</option>
                </select>
            <?php endif; ?>

            <input type="date" name="from_date" value="<?php echo e($from_date); ?>" title="From Date">
            <input type="date" name="to_date" value="<?php echo e($to_date); ?>" title="To Date">

            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($search) || !empty($direction) || !empty($from_date) || !empty($to_date)): ?>
                <a href="index.php" class="btn btn-secondary btn-sm" style="background:#888;">Reset</a>
            <?php endif; ?>
        </form>

        <div style="font-size: 13px; color: #666;">
            Transfers: <strong><?php echo mysqli_num_rows($result); ?></strong>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span>Transfer Dispatch Records</span>
            <span class="badge badge-info">Atomic Inventory Relocation</span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (mysqli_num_rows($result) === 0): ?>
                <div style="padding: 30px; text-align: center; color: #777;">
                    <p style="font-size: 15px; margin-bottom: 8px;">No stock transfers recorded.</p>
                    <?php if ($can_transfer): ?>
                        <a href="create.php" class="btn btn-primary btn-sm">+ Initiate First Stock Transfer</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table class="data-table" style="margin-top: 0; border: none;">
                    <thead>
                        <tr>
                            <th style="width: 110px;">Transfer #</th>
                            <th style="width: 100px;">Date</th>
                            <th>Sender (Source)</th>
                            <th>Receiver (Destination)</th>
                            <th>Product Transferred</th>
                            <th style="text-align: right; width: 110px;">Quantity</th>
                            <th style="width: 100px; text-align: center;">Status</th>
                            <th style="width: 100px; text-align: center;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): 
                            $is_sender = ((int)$row['from_user_id'] === (int)$current_user['id']);
                        ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?php echo e($row['id']); ?>" style="color: #2b5797; font-weight: bold; text-decoration: none;">
                                        <?php echo e($row['transfer_id']); ?>
                                    </a>
                                </td>
                                <td><?php echo date('d M Y', strtotime($row['transfer_date'])); ?></td>
                                <td>
                                    <strong><?php echo e($row['from_name']); ?></strong>
                                    <span class="badge-role badge-<?php echo strtolower($row['from_role']); ?>" style="font-size: 10px;">
                                        <?php echo e($row['from_role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo e($row['to_name']); ?></strong>
                                    <span class="badge-role badge-<?php echo strtolower($row['to_role']); ?>" style="font-size: 10px;">
                                        <?php echo e($row['to_role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?php echo e($row['product_name']); ?></strong>
                                    <div style="font-size: 11px; color: #777;">Code: <?php echo e($row['sku']); ?></div>
                                </td>
                                <td style="text-align: right; font-weight: bold;">
                                    <?php if (!is_admin()): ?>
                                        <span style="<?php echo $is_sender ? 'color: #c9302c;' : 'color: #2e7d32;'; ?>">
                                            <?php echo $is_sender ? '-' : '+'; ?><?php echo number_format($row['quantity']); ?> <?php echo e($row['unit']); ?>
                                        </span>
                                    <?php else: ?>
                                        <?php echo number_format($row['quantity']); ?> <?php echo e($row['unit']); ?>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-success">&check; <?php echo e($row['status']); ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <a href="view.php?id=<?php echo e($row['id']); ?>" class="btn-info" style="font-size: 11px;">View</a>
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
