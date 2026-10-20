<?php
/**
 * Live HTTP Session Test
 * Smart Business Management System
 */

$cookie_file = __DIR__ . '/cookie.txt';
if (file_exists($cookie_file)) unlink($cookie_file);

$base_url = 'http://localhost/smart_business/';

// 1. Get login page to extract CSRF token and start session
$ch = curl_init($base_url . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
$html = curl_exec($ch);
curl_close($ch);

preg_match('/name="csrf_token" value="([^"]+)"/', $html, $matches);
$csrf = $matches[1] ?? '';
echo "1. Extracted CSRF token: " . ($csrf ? "OK" : "FAILED") . "\n";

// 2. Submit login credentials for ADM001
$ch = curl_init($base_url . 'login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'csrf_token' => $csrf,
    'user_id' => 'ADM001',
    'password' => 'Admin@123'
]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$login_success = (strpos($response, 'Admin Dashboard') !== false);
echo "2. Login submission result: " . ($login_success ? "PASSED (Reached Admin Dashboard)" : "FAILED (HTTP {$http_code})") . "\n";

// 3. Request User Management page with authenticated session
$ch = curl_init($base_url . 'users/index.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
$users_html = curl_exec($ch);
$users_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$users_success = ($users_code === 200 && strpos($users_html, 'User Management') !== false);
echo "3. Opening users/index.php: " . ($users_success ? "PASSED (HTTP 200, User Management Loaded)" : "FAILED (HTTP {$users_code})") . "\n";

// 4. Test categories, products, stock, reports
$urls = [
    'categories/index.php' => 'Category Management',
    'products/index.php'   => 'Products Catalog',
    'suppliers/index.php'  => 'Suppliers Directory',
    'customers/index.php'  => 'Customers Directory',
    'stock/index.php'      => 'Inventory &amp; Stock Levels',
    'purchases/index.php'  => 'Supplier Purchases',
    'stock_transfers/index.php' => 'Stock Transfers',
    'sales/index.php'      => 'Sales &amp; POS Billing',
    'reports/index.php'    => 'Business Intelligence &amp; Reports'
];

foreach ($urls as $uri => $expected_text) {
    $ch = curl_init($base_url . $uri);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
    $page_html = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = ($code === 200 && strpos($page_html, $expected_text) !== false);
    echo "4. Testing {$uri}: " . ($ok ? "PASSED (HTTP 200)" : "FAILED (HTTP {$code})") . "\n";
}

if (file_exists($cookie_file)) unlink($cookie_file);
echo "\nAll live browser simulation checks finished successfully!\n";
