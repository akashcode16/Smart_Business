<?php
/**
 * Public Registration & Email Verification Live HTTP Test
 * Smart Business Management System
 */

require_once __DIR__ . '/../config/database.php';

$base_url = 'http://localhost/smart_business/';
$test_email = 'newretailer_' . time() . '@testdomain.local';
$test_pass = 'StrongPass@123';
$test_name = 'Test Retailer Agent';

$cookie_file = __DIR__ . '/cookie_reg.txt';
if (file_exists($cookie_file)) unlink($cookie_file);

// 1. Get register.php to grab CSRF token
$ch = curl_init($base_url . 'register.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';

// 2. Submit Registration Form
$ch = curl_init($base_url . 'register.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'full_name' => $test_name,
    'email' => $test_email,
    'password' => $test_pass,
    'confirm_password' => $test_pass
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$reg_response = curl_exec($ch);
curl_close($ch);

$reg_ok = (strpos($reg_response, 'Registration successful') !== false || strpos($reg_response, 'verify.php') !== false);
echo "1. Public Registration Submission: " . ($reg_ok ? "PASSED" : "FAILED") . "\n";

// 3. Retrieve token from database
$stmt = mysqli_prepare($conn, "SELECT id, user_id, verification_token, is_verified FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, "s", $test_email);
mysqli_stmt_execute($stmt);
$row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$row) {
    die("FAILED: User not found in database.\n");
}
echo "2. User Record Created in DB with ID {$row['user_id']}, is_verified = {$row['is_verified']}: PASSED\n";

$token = $row['verification_token'];

// 4. Hit verify.php?token=...
$ch = curl_init($base_url . 'verify.php?token=' . urlencode($token));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$verify_resp = curl_exec($ch);
curl_close($ch);

$verify_ok = (strpos($verify_resp, 'Verification Successful!') !== false || strpos($verify_resp, 'verified') !== false);
echo "3. Token Verification via verify.php: " . ($verify_ok ? "PASSED" : "FAILED") . "\n";

// Re-check database verification status
$stmt = mysqli_prepare($conn, "SELECT is_verified FROM users WHERE email = ?");
mysqli_stmt_bind_param($stmt, "s", $test_email);
mysqli_stmt_execute($stmt);
$v_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
echo "4. Database is_verified status now: " . ($v_row['is_verified'] == 1 ? "1 (Active & Verified - PASSED)" : "0 (FAILED)") . "\n";

// 5. Test login with newly registered & verified email
$ch = curl_init($base_url . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';

$ch = curl_init($base_url . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'user_id' => $test_email,
    'password' => $test_pass
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$login_resp = curl_exec($ch);
$login_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

$user_login_ok = (strpos($login_url, 'retailer/index.php') !== false);
echo "5. Login with new verified account: " . ($user_login_ok ? "PASSED (Redirected to Retailer Dashboard)" : "FAILED ({$login_url})") . "\n";

// Clean up test user
mysqli_query($conn, "DELETE FROM users WHERE email = '{$test_email}'");
if (file_exists($cookie_file)) unlink($cookie_file);

echo "\nRegistration & Verification test finished successfully!\n";
