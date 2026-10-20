<?php
/**
 * User Login Page
 * Smart Business Management System
 */

require_once 'config/database.php';
require_once 'includes/auth.php';

// Redirect if already logged in
redirect_if_logged_in();

$error_message = '';
$success_message = '';
$unverified_account = null;

// Check for logout or session messages
if (isset($_GET['logged_out'])) {
    $success_message = "You have been logged out successfully.";
}

if (isset($_GET['registered'])) {
    $success_message = "Registration successful! Please verify your email address to activate your account.";
}

if (isset($_GET['verified'])) {
    $success_message = "Your email has been verified successfully! You may now log in.";
}

if (isset($_SESSION['auth_error'])) {
    $error_message = $_SESSION['auth_error'];
    unset($_SESSION['auth_error']);
}

// Process login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_message = "Invalid security token. Please refresh and try again.";
    } else {
        $login_input = trim($_POST['user_id'] ?? '');
        $password_input = $_POST['password'] ?? '';

        if (empty($login_input) || empty($password_input)) {
            $error_message = "Please enter both User ID / Email and Password.";
        } else {
            $login_lower = strtolower($login_input);
            // Query user by user_id or email, including verification token and expiry
            $query = "SELECT u.id, u.user_id, u.full_name, u.email, u.password, u.role_id, u.status, u.is_verified, u.verification_token, u.token_expiry, r.role_name 
                      FROM users u 
                      JOIN roles r ON u.role_id = r.id 
                      WHERE u.user_id = ? OR LOWER(u.email) = ? OR LOWER(u.google_email) = ? 
                      LIMIT 1";

            if ($stmt = mysqli_prepare($conn, $query)) {
                mysqli_stmt_bind_param($stmt, "sss", $login_input, $login_lower, $login_lower);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                if ($user = mysqli_fetch_assoc($result)) {
                    // Check if password exists (could be null if google-only)
                    if (empty($user['password'])) {
                        $error_message = "This account was registered via Google Sign-In. Please click 'Continue with Google' below.";
                    } elseif (password_verify($password_input, $user['password'])) {
                        // Password is VALID. Now check if account is verified
                        if (isset($user['is_verified']) && (int)$user['is_verified'] === 0) {
                            // User authenticated their password, but email verification is pending.
                            // Ensure an active token exists; if expired or missing, regenerate securely.
                            $token = $user['verification_token'];
                            $now = date('Y-m-d H:i:s');
                            if (empty($token) || (!empty($user['token_expiry']) && $user['token_expiry'] < $now)) {
                                $token = bin2hex(random_bytes(32));
                                $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                                $up_tok = mysqli_prepare($conn, "UPDATE users SET verification_token = ?, token_expiry = ? WHERE id = ?");
                                mysqli_stmt_bind_param($up_tok, "ssi", $token, $token_expiry, $user['id']);
                                mysqli_stmt_execute($up_tok);
                                mysqli_stmt_close($up_tok);
                            }

                            // Build local verification URL
                            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                            $host = $_SERVER['HTTP_HOST'];
                            $verify_url = $protocol . "://" . $host . BASE_URL . "verify.php?token=" . urlencode($token);

                            $unverified_account = [
                                'email' => $user['email'],
                                'verify_url' => $verify_url
                            ];
                            $error_message = "Your email address has not been verified yet. Please check your inbox or verification link.";
                        } elseif ($user['status'] !== 'Active') {
                            $error_message = "Your account is inactive. Please contact the administrator.";
                        } else {
                            // Regenerate session ID to prevent session fixation
                            session_regenerate_id(true);

                            // Store session variables
                            $_SESSION['user_db_id'] = (int)$user['id'];
                            $_SESSION['user_id']    = $user['user_id'];
                            $_SESSION['full_name']  = $user['full_name'];
                            $_SESSION['role_id']    = (int)$user['role_id'];
                            $_SESSION['role_name']  = $user['role_name'];

                            mysqli_stmt_close($stmt);

                            // Redirect to the existing dashboard for their assigned role
                            redirect_if_logged_in();
                            exit;
                        }
                    } else {
                        // Invalid password - NEVER leak account status or verification tokens to unauthorized users
                        $error_message = "Invalid User ID/Email or Password.";
                    }
                } else {
                    $error_message = "Invalid User ID/Email or Password.";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_message = "A system error occurred. Please try again later.";
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
    <title>Login - Smart Business Management System</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo file_exists(__DIR__ . '/assets/css/style.css') ? filemtime(__DIR__ . '/assets/css/style.css') : time(); ?>">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-badge">PORTAL ACCESS</div>
                <h1>Smart Business Management</h1>
                <p>Stockiest &bull; Distributor &bull; Retailer</p>
            </div>

            <?php if (!empty($unverified_account)): ?>
                <div class="alert alert-warning">
                    <div style="font-weight: 700; margin-bottom: 4px;">Email Verification Pending</div>
                    <div style="margin-bottom: 8px;">
                        Your email (<strong><?php echo htmlspecialchars($unverified_account['email']); ?></strong>) is registered, but requires verification before sign-in.
                    </div>
                    <div style="background: #ffffff; padding: 12px; border-radius: 5px; border: 1px solid #fed7aa; margin-top: 8px;">
                        <span style="display: block; font-size: 11.5px; color: #9a3412; margin-bottom: 8px;">
                            <strong>Local XAMPP Development Notice:</strong> Outbound SMTP is not configured in local PHP by default. Since you entered the correct password, you can activate your account directly:
                        </span>
                        <a href="<?php echo htmlspecialchars($unverified_account['verify_url']); ?>" class="btn btn-success btn-sm btn-block" style="text-align: center; text-decoration: none; padding: 8px 12px;">
                            &check; Verify &amp; Activate Account Now
                        </a>
                    </div>
                </div>
            <?php elseif (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" autocomplete="off">
                <?php echo csrf_input(); ?>

                <div class="form-group">
                    <label for="user_id">User ID or Email Address</label>
                    <input type="text" 
                           id="user_id" 
                           name="user_id" 
                           class="form-control" 
                           placeholder="Enter User ID or Registered Email" 
                           value="<?php echo isset($_POST['user_id']) ? htmlspecialchars($_POST['user_id']) : ''; ?>" 
                           required 
                           autofocus>
                </div>

                <div class="form-group">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="password" style="margin-bottom: 0;">Password</label>
                    </div>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Enter your password" 
                           required>
                    <div class="checkbox-group" style="margin-top: 8px;">
                        <input type="checkbox" id="show_password" onclick="togglePasswordVisibility()">
                        <label for="show_password" style="font-weight: normal; margin-bottom: 0; cursor: pointer; font-size: 12px; color: #64748b;">Show Password</label>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 22px;">
                    <button type="submit" class="btn btn-primary btn-block">Sign In</button>
                </div>
            </form>

            <div style="text-align: center; margin-top: 18px; font-size: 13px;">
                <span style="color: #64748b;">Don't have an account? </span>
                <a href="register.php" style="color: #1e40af; font-weight: 600; text-decoration: none;">Create Account</a>
            </div>

            <div style="text-align: center; margin-top: 10px; font-size: 12px;">
                <a href="resend_verification.php" style="color: #64748b; text-decoration: none;">Need to activate or resend verification link?</a>
            </div>

            <div class="auth-divider">
                <span>OR</span>
            </div>

            <a href="google_login.php" class="btn-google">
                <svg viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
                Continue with Google
            </a>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            var pwdField = document.getElementById("password");
            if (pwdField.type === "password") {
                pwdField.type = "text";
            } else {
                pwdField.type = "password";
            }
        }
    </script>
</body>
</html>