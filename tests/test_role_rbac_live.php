<?php
/**
 * Role-Based Access Control Live HTTP Test
 * Smart Business Management System
 */

$base_url = 'http://localhost/smart_business/';

function test_role_session($user_id, $password, $expected_dash, $allowed_url, $forbidden_url) {
    global $base_url;
    $cookie_file = __DIR__ . "/cookie_{$user_id}.txt";
    if (file_exists($cookie_file)) unlink($cookie_file);

    // 1. Get CSRF token
    $ch = curl_init($base_url . 'login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    $html = curl_exec($ch);
    curl_close($ch);

    preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
    $csrf = $m[1] ?? '';

    // 2. Login
    $ch = curl_init($base_url . 'login.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'csrf_token' => $csrf,
        'user_id' => $user_id,
        'password' => $password
    ]));
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $dash_html = curl_exec($ch);
    $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    $login_ok = (strpos($effective_url, $expected_dash) !== false);
    echo "[{$user_id}] Login Redirect to {$expected_dash}: " . ($login_ok ? "PASSED" : "FAILED ({$effective_url})") . "\n";

    // 3. Test Allowed URL
    $ch = curl_init($base_url . $allowed_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $allowed_content = curl_exec($ch);
    $allowed_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $allow_ok = ($allowed_code === 200);
    echo "[{$user_id}] Access Allowed ({$allowed_url}): " . ($allow_ok ? "PASSED (200)" : "FAILED ({$allowed_code})") . "\n";

    // 4. Test Forbidden URL (should return 302 redirect or 403)
    $ch = curl_init($base_url . $forbidden_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $forbid_content = curl_exec($ch);
    $forbid_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);

    $forbid_ok = ($forbid_code === 302 || $forbid_code === 403);
    echo "[{$user_id}] Access Forbidden ({$forbidden_url}): " . ($forbid_ok ? "PASSED (Blocked with {$forbid_code})" : "FAILED ({$forbid_code})") . "\n";

    if (file_exists($cookie_file)) unlink($cookie_file);
}

echo "=== TESTING LIVE RBAC PROTECTION OVER HTTP ===\n\n";

// Test Stockiest
test_role_session('STK001', 'Stock@123', 'stockiest/index.php', 'purchases/create.php', 'users/create.php');

// Test Distributor
test_role_session('DST001', 'Dist@123', 'distributor/index.php', 'stock_transfers/create.php', 'purchases/create.php');

// Test Retailer
test_role_session('RET001', 'Retail@123', 'retailer/index.php', 'sales/create.php', 'purchases/create.php');

echo "\nRBAC Live tests complete.\n";
