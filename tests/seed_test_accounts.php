<?php
/**
 * Test Accounts Seeder for Multi-Role Testing
 * Smart Business Management System
 */

require_once __DIR__ . '/../config/database.php';

echo "Seeding/Verifying Standard Multi-Role Test Accounts...\n";

$roles_res = mysqli_query($conn, "SELECT id, role_name FROM roles");
$role_map = [];
while ($r = mysqli_fetch_assoc($roles_res)) {
    $role_map[$r['role_name']] = (int)$r['id'];
}

$test_users = [
    [
        'user_id' => 'ADM001',
        'full_name' => 'System Administrator',
        'email' => 'akashi24cb@psnacet.edu.in',
        'password' => 'Admin@123',
        'role_name' => 'Admin',
        'status' => 'Active',
        'is_verified' => 1
    ],
    [
        'user_id' => 'STK001',
        'full_name' => 'Apex Central Stockiest',
        'email' => 'stockiest@smartbusiness.local',
        'password' => 'Stock@123',
        'role_name' => 'Stockiest',
        'status' => 'Active',
        'is_verified' => 1
    ],
    [
        'user_id' => 'DST001',
        'full_name' => 'Metro Regional Distributor',
        'email' => 'distributor@smartbusiness.local',
        'password' => 'Dist@123',
        'role_name' => 'Distributor',
        'status' => 'Active',
        'is_verified' => 1
    ],
    [
        'user_id' => 'RET001',
        'full_name' => 'City Center Retail Store',
        'email' => 'retailer@smartbusiness.local',
        'password' => 'Retail@123',
        'role_name' => 'Retailer',
        'status' => 'Active',
        'is_verified' => 1
    ]
];

foreach ($test_users as $tu) {
    $chk = mysqli_query($conn, "SELECT id, password FROM users WHERE user_id = '{$tu['user_id']}'");
    $role_id = $role_map[$tu['role_name']];
    $pwd_hash = password_hash($tu['password'], PASSWORD_BCRYPT);

    if ($row = mysqli_fetch_assoc($chk)) {
        // Update password hash if needed
        if (empty($row['password']) || !password_verify($tu['password'], $row['password'])) {
            $up = mysqli_prepare($conn, "UPDATE users SET password = ?, status = 'Active', is_verified = 1 WHERE id = ?");
            mysqli_stmt_bind_param($up, "si", $pwd_hash, $row['id']);
            mysqli_stmt_execute($up);
            mysqli_stmt_close($up);
            echo "Updated existing user {$tu['user_id']} ({$tu['role_name']}) with active credentials.\n";
        } else {
            echo "User {$tu['user_id']} ({$tu['role_name']}) already configured with valid password.\n";
        }
    } else {
        $ins = mysqli_prepare($conn, "INSERT INTO users (user_id, full_name, email, password, role_id, status, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($ins, "ssssisi", $tu['user_id'], $tu['full_name'], $tu['email'], $pwd_hash, $role_id, $tu['status'], $tu['is_verified']);
        mysqli_stmt_execute($ins);
        mysqli_stmt_close($ins);
        echo "Created test user {$tu['user_id']} ({$tu['role_name']}).\n";
    }
}

echo "Test account setup complete!\n";
