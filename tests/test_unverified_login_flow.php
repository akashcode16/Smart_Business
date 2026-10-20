<?php
/**
 * Unverified Login Flow & Security Test
 * Smart Business Management System
 */

require_once __DIR__ . '/../config/database.php';

$base_url = 'http://localhost/smart_business/';
$test_email = 'unverified_sec_' . time() . '@testdomain.local';
$test_pass = 'SecTestPass@123';
$test_name = 'Security Unverified User';

$cookie_file = __DIR__ . '/cookie_unverified.txt';
if (file_exists($cookie_file)) unlink($cookie_file);

// 1. Create unverified test account in DB directly
$token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
$pass_hash = password_hash($test_pass, PASSWORD_BCRYPT);
$user_id = 'RET999';

mysqli_query($conn, "DELETE FROM users WHERE user_id = '{$user_id}' OR email = '{$test_email}'");

$ins = mysqli_prepare($conn, "INSERT INTO users (user_id, full_name, email, password, role_id, status, is_verified, verification_token, token_expiry) VALUES (?, ?, ?, ?, 4, 'Active', 0, ?, ?)");
mysqli_stmt_bind_param($ins, "ssssss", $user_id, $test_name, $test_email, $pass_hash, $token, $expiry);
mysqli_stmt_execute($ins);
mysqli_stmt_close($ins);

echo "1. Created unverified user in database: RET999 ({$test_email}), is_verified=0\n";

// Function to fetch CSRF token
function get_csrf($url, $cookie_file) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    $html = curl_exec($ch);
    curl_close($ch);
    preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
    return $m[1] ?? '';
}

// 2. Test login with wrong password
$csrf = get_csrf($base_url . 'login.php', $cookie_file);
$ch = curl_init($base_url . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'user_id' => $user_id,
    'password' => 'WrongPassword!'
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
$resp = curl_exec($ch);
curl_close($ch);

$leak_token = (strpos($resp, $token) !== false || strpos($resp, 'verify.php?token=') !== false);
$rejected_wrong = (strpos($resp, 'Invalid User ID/Email or Password.') !== false);

echo "2. Login with wrong password rejected: " . ($rejected_wrong ? "PASSED" : "FAILED") . "\n";
echo "3. No verification token leaked to attacker with wrong password: " . (!$leak_token ? "PASSED" : "FAILED (Security Leak)") . "\n";

// 3. Test login with correct password on unverified account
$csrf = get_csrf($base_url . 'login.php', $cookie_file);
$ch = curl_init($base_url . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'user_id' => $user_id,
    'password' => $test_pass
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
$resp = curl_exec($ch);
curl_close($ch);

$shows_unverified_alert = (strpos($resp, 'Email Verification Pending') !== false || strpos($resp, 'Your email address has not been verified yet') !== false);
preg_match('/href="([^"]*verify\.php\?token=[^"]+)"/', $resp, $verify_matches);
$activation_url = $verify_matches[1] ?? '';

echo "4. Correct password recognizes unverified status: " . ($shows_unverified_alert ? "PASSED" : "FAILED") . "\n";
echo "5. Authenticated user receives local activation link: " . (!empty($activation_url) ? "PASSED" : "FAILED") . "\n";

// 4. Follow activation link
if (!empty($activation_url)) {
    $ch = curl_init($activation_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $verify_resp = curl_exec($ch);
    curl_close($ch);
    $act_success = (strpos($verify_resp, 'Verification Successful!') !== false || strpos($verify_resp, 'verified') !== false);
    echo "6. Click activation link verifies account: " . ($act_success ? "PASSED" : "FAILED") . "\n";
}

// 5. Check database is_verified
$chk = mysqli_query($conn, "SELECT is_verified FROM users WHERE user_id = '{$user_id}'");
$r = mysqli_fetch_assoc($chk);
echo "7. Database is_verified status after click: " . ($r['is_verified'] == 1 ? "1 (Active & Verified - PASSED)" : "0 (FAILED)") . "\n";

// 6. Login now succeeds and redirects to retailer/index.php
$csrf = get_csrf($base_url . 'login.php', $cookie_file);
$ch = curl_init($base_url . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'user_id' => $user_id,
    'password' => $test_pass
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$login_resp = curl_exec($ch);
$login_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

$redirect_ok = (strpos($login_url, 'retailer/index.php') !== false);
echo "8. Subsequent login redirects to retailer dashboard: " . ($redirect_ok ? "PASSED ({$login_url})" : "FAILED ({$login_url})") . "\n";

// Cleanup
mysqli_query($conn, "DELETE FROM users WHERE user_id = '{$user_id}'");
if (file_exists($cookie_file)) unlink($cookie_file);

echo "\nAll unverified login flow and security checks completed!\n";
