<?php
/**
 * Safe Database Migration Script
 * Smart Business Management System
 */

require_once __DIR__ . '/../config/database.php';

echo "Running migrations for smart_business...\n";

// 1. Add email column to users if not exists
$check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'email'");
if (mysqli_num_rows($check) === 0) {
    $sql = "ALTER TABLE users ADD COLUMN email VARCHAR(100) NULL AFTER full_name";
    if (mysqli_query($conn, $sql)) {
        echo "Added column 'email' to users table.\n";
        // Backfill email from google_email
        mysqli_query($conn, "UPDATE users SET email = google_email WHERE email IS NULL AND google_email IS NOT NULL");
        // Add unique index on email
        mysqli_query($conn, "ALTER TABLE users ADD UNIQUE KEY unique_email (email)");
        echo "Populated email from google_email and added UNIQUE index.\n";
    } else {
        echo "Error adding email column: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Column 'email' already exists in users.\n";
}

// 2. Add is_verified, verification_token, token_expiry to users
$check = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'is_verified'");
if (mysqli_num_rows($check) === 0) {
    $sql = "ALTER TABLE users 
            ADD COLUMN is_verified TINYINT(1) NOT NULL DEFAULT 1 AFTER status,
            ADD COLUMN verification_token VARCHAR(100) NULL AFTER is_verified,
            ADD COLUMN token_expiry DATETIME NULL AFTER verification_token";
    if (mysqli_query($conn, $sql)) {
        echo "Added verification columns to users table.\n";
    } else {
        echo "Error adding verification columns: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Verification columns already exist in users.\n";
}

// 3. Add created_by to customers if not exists
$check = mysqli_query($conn, "SHOW COLUMNS FROM customers LIKE 'created_by'");
if (mysqli_num_rows($check) === 0) {
    $sql = "ALTER TABLE customers 
            ADD COLUMN created_by INT(11) NULL AFTER address,
            ADD KEY fk_customers_created_by (created_by),
            ADD CONSTRAINT fk_customers_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE";
    if (mysqli_query($conn, $sql)) {
        echo "Added created_by column and FK to customers table.\n";
    } else {
        echo "Error adding created_by to customers: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Column 'created_by' already exists in customers.\n";
}

// 4. Create stock_movements table for audit & traceability
$sql = "CREATE TABLE IF NOT EXISTS stock_movements (
    id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    product_id INT(11) NOT NULL,
    movement_type ENUM('Opening', 'Purchase', 'Transfer In', 'Transfer Out', 'Sale', 'Adjustment') NOT NULL,
    quantity INT(11) NOT NULL,
    balance_before INT(11) NOT NULL DEFAULT 0,
    balance_after INT(11) NOT NULL DEFAULT 0,
    reference_id VARCHAR(50) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY fk_movements_user (user_id),
    KEY fk_movements_product (product_id),
    CONSTRAINT fk_movements_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_movements_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
if (mysqli_query($conn, $sql)) {
    echo "Checked/created stock_movements table successfully.\n";
} else {
    echo "Error creating stock_movements: " . mysqli_error($conn) . "\n";
}

// 5. Add user_id to payments table if not exists
$check = mysqli_query($conn, "SHOW COLUMNS FROM payments LIKE 'user_id'");
if (mysqli_num_rows($check) === 0) {
    $sql = "ALTER TABLE payments 
            ADD COLUMN user_id INT(11) NULL AFTER reference_id,
            ADD KEY fk_payments_user (user_id),
            ADD CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE";
    if (mysqli_query($conn, $sql)) {
        echo "Added user_id column to payments table.\n";
    } else {
        echo "Error adding user_id to payments: " . mysqli_error($conn) . "\n";
    }
} else {
    echo "Column 'user_id' already exists in payments.\n";
}

// 6. Ensure default Admin account ADM001 has a working hashed password for local credential testing
// Default password: Admin@123
$default_admin_hash = password_hash('Admin@123', PASSWORD_BCRYPT);
$check_admin = mysqli_query($conn, "SELECT password, email FROM users WHERE user_id = 'ADM001'");
if ($row = mysqli_fetch_assoc($check_admin)) {
    if (empty($row['password'])) {
        $update = mysqli_prepare($conn, "UPDATE users SET password = ?, is_verified = 1 WHERE user_id = 'ADM001'");
        mysqli_stmt_bind_param($update, "s", $default_admin_hash);
        mysqli_stmt_execute($update);
        mysqli_stmt_close($update);
        echo "Updated ADM001 with default password hash (Admin@123) and is_verified=1.\n";
    }
    if (empty($row['email'])) {
        mysqli_query($conn, "UPDATE users SET email = 'akashi24cb@psnacet.edu.in' WHERE user_id = 'ADM001'");
        echo "Updated ADM001 email.\n";
    }
}

echo "Migration completed successfully!\n";
