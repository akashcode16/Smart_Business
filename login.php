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

// Check for logout or session messages
if (isset($_GET['logged_out'])) {
    $success_message = "You have been logged out successfully.";
}

if (isset($_SESSION['auth_error'])) {
    $error_message = $_SESSION['auth_error'];
    unset($_SESSION['auth_error']);
}

// Process login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id_input = trim($_POST['user_id'] ?? '');
    $password_input = $_POST['password'] ?? '';

    if (empty($user_id_input) || empty($password_input)) {
        $error_message = "Please enter both User ID and Password.";
    } else {
        // Query user using prepared statement
        $query = "SELECT u.id, u.user_id, u.full_name, u.password, u.role_id, u.status, r.role_name 
                  FROM users u 
                  JOIN roles r ON u.role_id = r.id 
                  WHERE u.user_id = ? 
                  LIMIT 1";

        if ($stmt = mysqli_prepare($conn, $query)) {
            mysqli_stmt_bind_param($stmt, "s", $user_id_input);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($user = mysqli_fetch_assoc($result)) {
                // Verify password
                if (password_verify($password_input, $user['password'])) {
                    // Check if account is active
                    if ($user['status'] !== 'Active') {
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

                        // Close statement & connection
                        mysqli_stmt_close($stmt);

                        // Redirect by role
                        redirect_if_logged_in();
                        exit;
                    }
                } else {
                    $error_message = "Invalid User ID or Password.";
                }
            } else {
                $error_message = "Invalid User ID or Password.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error_message = "A system error occurred. Please try again later.";
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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
    <div class="auth-wrapper">
        <div class="auth-card">
            <div class="auth-header">
                <h1>Smart Business Management System</h1>
                <p>Stockiest &bull; Distributor &bull; Retailer</p>
            </div>

            <?php if (!empty($error_message)): ?>
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
                <div class="form-group">
                    <label for="user_id">User ID</label>
                    <input type="text" 
                           id="user_id" 
                           name="user_id" 
                           class="form-control" 
                           placeholder="Enter your User ID" 
                           value="<?php echo isset($_POST['user_id']) ? htmlspecialchars($_POST['user_id']) : ''; ?>" 
                           required 
                           autofocus>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-control" 
                           placeholder="Enter your Password" 
                           required>
                    <div class="checkbox-group">
                        <input type="checkbox" id="show_password" onclick="togglePasswordVisibility()">
                        <label for="show_password" style="font-weight: normal; margin-bottom: 0; cursor: pointer;">Show Password</label>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 25px;">
                    <button type="submit" class="btn btn-primary btn-block">LOGIN</button>
                </div>
            </form>

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
