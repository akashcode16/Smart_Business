<?php
/**
 * Email Verification Handler
 * Smart Business Management System
 */

require_once 'config/database.php';
require_once 'includes/auth.php';

$token = trim($_GET['token'] ?? '');
$status = 'error';
$message = '';

if (empty($token)) {
    $message = "No verification token provided.";
} else {
    // Look up token in database
    $query = "SELECT id, user_id, full_name, email, is_verified, token_expiry 
              FROM users 
              WHERE verification_token = ? 
              LIMIT 1";

    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "s", $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        if ($user = mysqli_fetch_assoc($result)) {
            // Check if already verified
            if ((int) $user['is_verified'] === 1) {
                $status = 'success';
                $message = "Your email address is already verified. You can log in directly.";
            } else {
                // Check expiry
                $now = date('Y-m-d H:i:s');
                if (!empty($user['token_expiry']) && $user['token_expiry'] < $now) {
                    $status = 'error';
                    $message = "This verification token has expired. Please contact support or request a new verification.";
                } else {
                    // Activate user and clear verification token
                    $update = mysqli_prepare($conn, "UPDATE users SET is_verified = 1, verification_token = NULL, token_expiry = NULL WHERE id = ?");
                    mysqli_stmt_bind_param($update, "i", $user['id']);
                    if (mysqli_stmt_execute($update)) {
                        $status = 'success';
                        $message = "Congratulations! Your email has been verified and your account is now active.";
                    } else {
                        $status = 'error';
                        $message = "Failed to update account status. Please try again.";
                    }
                    mysqli_stmt_close($update);
                }
            }
        } else {
            $message = "Invalid or unrecognized verification token.";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "A database error occurred. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Smart Business Management System</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time(); ?>">
</head>

<body>
    <div class="auth-wrapper">
        <div class="auth-card" style="max-width: 460px; text-align: center;">
            <div class="auth-header">
                <h1>Smart Business</h1>
                <p>Email Account Verification</p>
            </div>

            <?php if ($status === 'success'): ?>
                <div class="alert alert-success" style="text-align: left;">
                    <strong>Verification Successful!</strong>
                    <p style="margin-top: 6px;"><?php echo htmlspecialchars($message); ?></p>
                </div>
                <div style="margin-top: 20px;">
                    <a href="login.php?verified=1" class="btn btn-primary btn-block">Proceed to Login</a>
                </div>
            <?php else: ?>
                <div class="alert alert-danger" style="text-align: left;">
                    <strong>Verification Error</strong>
                    <p style="margin-top: 6px;"><?php echo htmlspecialchars($message); ?></p>
                </div>
                <div style="margin-top: 20px;">
                    <a href="login.php" class="btn btn-secondary btn-block">Return to Login</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>