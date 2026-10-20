<?php
/**
 * Resend Email Verification & Local Activation Helper
 * Smart Business Management System
 */

require_once 'config/database.php';
require_once 'includes/auth.php';

// Redirect if already logged in
redirect_if_logged_in();

$error_message = '';
$success_message = '';
$verify_link = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $login_input = trim($_POST['user_id'] ?? '');
        $password_input = $_POST['password'] ?? '';

        if (empty($login_input) || empty($password_input)) {
            $error_message = "Please enter both your User ID or Email, and your password.";
        } else {
            $login_lower = strtolower($login_input);
            $query = "SELECT id, user_id, full_name, email, password, is_verified 
                      FROM users 
                      WHERE user_id = ? OR LOWER(email) = ? OR LOWER(google_email) = ? 
                      LIMIT 1";

            if ($stmt = mysqli_prepare($conn, $query)) {
                mysqli_stmt_bind_param($stmt, "sss", $login_input, $login_lower, $login_lower);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($user = mysqli_fetch_assoc($result)) {
                    if (empty($user['password'])) {
                        $error_message = "This account was registered via Google Sign-In. You can log in directly with Google.";
                    } elseif (password_verify($password_input, $user['password'])) {
                        if ((int)$user['is_verified'] === 1) {
                            $success_message = "Your email address is already verified! You can log in directly.";
                        } else {
                            // Generate new token & 24h expiry
                            $token = bin2hex(random_bytes(32));
                            $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));

                            $up = mysqli_prepare($conn, "UPDATE users SET verification_token = ?, token_expiry = ? WHERE id = ?");
                            mysqli_stmt_bind_param($up, "ssi", $token, $token_expiry, $user['id']);
                            mysqli_stmt_execute($up);
                            mysqli_stmt_close($up);

                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                            $host = $_SERVER['HTTP_HOST'];
                            $verify_link = $protocol . "://" . $host . BASE_URL . "verify.php?token=" . urlencode($token);

                            // Attempt PHP mail()
                            $subject = "Verify Your Account - Smart Business Management System";
                            $message = "Hello {$user['full_name']},\r\n\r\nPlease verify your email using this link:\r\n{$verify_link}\r\n\r\nExpires in 24 hours.";
                            $headers = "From: noreply@smartbusiness.local\r\nReply-To: noreply@smartbusiness.local\r\nX-Mailer: PHP/" . phpversion();
                            @mail($user['email'], $subject, $message, $headers);

                            $success_message = "A new verification link has been generated for " . htmlspecialchars($user['email']) . ".";
                        }
                    } else {
                        $error_message = "Invalid credentials. Please verify your User ID/Email and password.";
                    }
                } else {
                    $error_message = "Invalid credentials. Please verify your User ID/Email and password.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "Database query error. Please try again.";
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
    <title>Resend Verification - Smart Business Management System</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time(); ?>">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 480px;">
            <div class="auth-header">
                <div class="auth-badge">ACCOUNT ACTIVATION</div>
                <h1>Email Verification</h1>
                <p>Activate your account or regenerate verification token</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <p><strong><?php echo htmlspecialchars($success_message); ?></strong></p>
                    <?php if (!empty($verify_link)): ?>
                        <div style="margin-top: 10px; padding: 10px; background: #ffffff; border: 1px solid #ceead6; border-radius: 4px;">
                            <span style="display: block; font-size: 11.5px; color: #166534; margin-bottom: 6px;">
                                <strong>Local Development Link:</strong>
                            </span>
                            <a href="<?php echo htmlspecialchars($verify_link); ?>" class="btn btn-success btn-sm btn-block" style="text-align: center; text-decoration: none;">
                                &check; Click to Verify &amp; Activate Now
                            </a>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 12px; text-align: center;">
                        <a href="login.php" class="btn btn-secondary btn-sm">Return to Login</a>
                    </div>
                </div>
            <?php else: ?>

                <form action="resend_verification.php" method="POST" autocomplete="off">
                    <?php echo csrf_input(); ?>

                    <div class="form-group">
                        <label for="user_id">User ID or Registered Email Address</label>
                        <input type="text" id="user_id" name="user_id" class="form-control" placeholder="e.g. RET002 or name@example.com" required autofocus>
                    </div>

                    <div class="form-group">
                        <label for="password">Account Password</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter your account password" required>
                        <small style="color: #64748b; font-size: 11.5px; margin-top: 4px; display: block;">
                            For security, your password is required to verify account ownership.
                        </small>
                    </div>

                    <div class="form-group" style="margin-top: 20px;">
                        <button type="submit" class="btn btn-primary btn-block">Generate Verification Link</button>
                    </div>
                </form>

                <div style="text-align: center; margin-top: 16px; font-size: 13px;">
                    <a href="login.php" style="color: #1e40af; font-weight: 600; text-decoration: none;">&larr; Back to Login</a>
                </div>

            <?php endif; ?>
        </div>
    </div>
</body>
</html>
