<?php
/**
 * Public User Registration
 * Smart Business Management System
 */

require_once 'config/database.php';
require_once 'includes/auth.php';

// Redirect if already logged in
redirect_if_logged_in();

$error_message = '';
$success_message = '';
$verification_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
            $error_message = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please provide a valid email address.";
        } elseif (strlen($password) < 6) {
            $error_message = "Password must be at least 6 characters long.";
        } elseif ($password !== $confirm_password) {
            $error_message = "Passwords do not match.";
        } else {
            // Check if email already exists
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE LOWER(email) = ? OR LOWER(google_email) = ? LIMIT 1");
            mysqli_stmt_bind_param($check_stmt, "ss", $email, $email);
            mysqli_stmt_execute($check_stmt);
            $check_res = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_res) > 0) {
                $error_message = "An account with this email address is already registered.";
                mysqli_stmt_close($check_stmt);
            } else {
                mysqli_stmt_close($check_stmt);

                // Fetch safe default role: Retailer
                $role_stmt = mysqli_prepare($conn, "SELECT id FROM roles WHERE role_name = 'Retailer' LIMIT 1");
                mysqli_stmt_execute($role_stmt);
                $role_res = mysqli_stmt_get_result($role_stmt);
                $role_row = mysqli_fetch_assoc($role_res);
                $role_id = $role_row ? (int) $role_row['id'] : 4;
                mysqli_stmt_close($role_stmt);

                // Generate User ID: RET001, RET002...
                $prefix = 'RET';
                $id_query = "SELECT user_id FROM users WHERE user_id LIKE '{$prefix}%' ORDER BY id DESC LIMIT 1";
                $id_res = mysqli_query($conn, $id_query);
                $next_num = 1;
                if ($id_res && $row = mysqli_fetch_assoc($id_res)) {
                    $num_part = (int) substr($row['user_id'], strlen($prefix));
                    $next_num = $num_part + 1;
                }
                $user_id = sprintf("%s%03d", $prefix, $next_num);

                // Hash password
                $password_hash = password_hash($password, PASSWORD_BCRYPT);

                // Generate secure expiring email verification token
                $verification_token = bin2hex(random_bytes(32));
                // 24 hours expiry
                $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

                // New account created with is_verified = 0
                $insert_query = "INSERT INTO users (user_id, full_name, email, password, role_id, status, is_verified, verification_token, token_expiry) 
                                VALUES (?, ?, ?, ?, ?, 'Active', 0, ?, ?)";

                if ($stmt = mysqli_prepare($conn, $insert_query)) {
                    mysqli_stmt_bind_param($stmt, "ssssiss", $user_id, $full_name, $email, $password_hash, $role_id, $verification_token, $token_expiry);

                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_stmt_close($stmt);

                        // Build verification URL
                        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                        $host = $_SERVER['HTTP_HOST'];
                        $verify_url = $protocol . "://" . $host . BASE_URL . "verify.php?token=" . urlencode($verification_token);

                        // Attempt sending email via standard PHP mail()
                        $subject = "Verify Your Account - Smart Business Management System";
                        $message = "Hello {$full_name},\r\n\r\nThank you for registering. Please click the link below to verify your account:\r\n{$verify_url}\r\n\r\nThis link will expire in 24 hours.\r\n\r\nSmart Business Management System";
                        $headers = "From: noreply@smartbusiness.local\r\nReply-To: noreply@smartbusiness.local\r\nX-Mailer: PHP/" . phpversion();

                        // Suppress mail warning if local SMTP not configured
                        @mail($email, $subject, $message, $headers);

                        $success_message = "Your account has been registered successfully!";
                        $verification_link = $verify_url;
                    } else {
                        $error_message = "Failed to register account. Please try again.";
                        mysqli_stmt_close($stmt);
                    }
                } else {
                    $error_message = "System error during registration. Please try again.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Smart Business Management System</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time(); ?>">
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 480px;">
            <div class="auth-header">
                <h1>Smart Business Registration</h1>
                <p>Register as a Business Retailer</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <p><strong><?php echo htmlspecialchars($success_message); ?></strong></p>
                    <p style="margin-top: 8px;">A verification email has been dispatched. For local XAMPP environments where
                        SMTP may not be configured, use the direct activation link below:</p>
                    <div
                        style="margin-top: 12px; padding: 10px; background: #ffffff; border: 1px solid #ceead6; border-radius: 4px; word-break: break-all;">
                        <a href="<?php echo htmlspecialchars($verification_link); ?>"
                            class="btn btn-success btn-sm btn-block" style="text-align: center; text-decoration: none;">
                            &check; Click Here to Verify &amp; Activate Account
                        </a>
                    </div>
                    <div style="margin-top: 15px; text-align: center;">
                        <a href="login.php" class="btn btn-secondary btn-sm">Proceed to Login</a>
                    </div>
                </div>
            <?php else: ?>

                <form action="register.php" method="POST" autocomplete="off">
                    <?php echo csrf_input(); ?>

                    <div class="form-group">
                        <label for="full_name">Full Name <span style="color: #c9302c;">*</span></label>
                        <input type="text" id="full_name" name="full_name" class="form-control"
                            placeholder="Enter your full business or personal name"
                            value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                            required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address <span style="color: #c9302c;">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="name@example.com"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                        <small style="color: #666; font-size: 11px;">Verification token will be generated for this
                            email.</small>
                    </div>

                    <div class="form-group">
                        <label for="password">Password (Min. 6 characters) <span style="color: #c9302c;">*</span></label>
                        <input type="password" id="password" name="password" class="form-control"
                            placeholder="Create password" required>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span style="color: #c9302c;">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                            placeholder="Confirm your password" required>
                    </div>

                    <div class="form-group" style="margin-top: 25px;">
                        <button type="submit" class="btn btn-primary btn-block">REGISTER ACCOUNT</button>
                    </div>
                </form>

                <div style="text-align: center; margin-top: 15px; font-size: 13px;">
                    <span>Already have an account? </span>
                    <a href="login.php" style="color: #2b5797; font-weight: bold; text-decoration: none;">Login here</a>
                </div>

            <?php endif; ?>
        </div>
    </div>
</body>

</html>