<?php
$cookie = __DIR__ . '/cookie_adm_render.txt';
if (file_exists($cookie)) unlink($cookie);

$ch = curl_init('http://localhost/smart_business/login.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
$html = curl_exec($ch);
preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m);
$csrf = $m[1] ?? '';

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['csrf_token' => $csrf, 'user_id' => 'ADM001', 'password' => 'Admin@123']));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($ch);
$login_eff_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
echo "LOGIN EFFECTIVE URL: " . $login_eff_url . "\n";
echo "LOGIN RESP: " . substr($res, 0, 300) . "\n";
$admin_html = $res;
echo "ADMIN HTML LENGTH: " . strlen($admin_html) . "\n";

// Find stat-card-row in HTML
if (preg_match('/<div class="stat-card-row">.*?<\/div>\s*<\/div>/s', $admin_html, $m_row)) {
    echo "FOUND STAT ROW HTML:\n" . $m_row[0] . "\n\n";
} else {
    echo "stat-card-row NOT found in HTML!\n";
}

// Check CSS link
preg_match_all('/<link[^>]+>/', $admin_html, $links);
echo "CSS LINKS FOUND:\n";
print_r($links[0]);

// Now check if CSS URL resolves to 200 OK
preg_match('/href="([^"]+style\.css[^"]*)"/', $admin_html, $m_css);
$css_href = $m_css[1] ?? '';
echo "CSS HREF: " . $css_href . "\n";

if ($css_href) {
    if (strpos($css_href, 'http') !== 0) {
        $css_url = 'http://localhost' . $css_href;
    } else {
        $css_url = $css_href;
    }
    echo "RESOLVED CSS URL: " . $css_url . "\n";
    $ch3 = curl_init($css_url);
    curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch3, CURLOPT_HEADER, true);
    $css_resp = curl_exec($ch3);
    $css_code = curl_getinfo($ch3, CURLINFO_HTTP_CODE);
    $css_type = curl_getinfo($ch3, CURLINFO_CONTENT_TYPE);
    curl_close($ch3);
    echo "CSS HTTP CODE: " . $css_code . " (Content-Type: " . $css_type . ")\n";
    echo "CSS LENGTH: " . strlen($css_resp) . "\n";
}

if (file_exists($cookie)) unlink($cookie);
