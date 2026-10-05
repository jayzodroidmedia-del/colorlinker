<?php
// ColorLinker Security Configuration

// 1. Database Connection Details
define('DB_HOST', 'localhost');
define('DB_NAME', 'colorlinker'); // Change this to your cPanel database name
define('DB_USER', 'colorlinker');  // Change this to your cPanel database user
define('DB_PASS', 'yDUYbIW8CfWg1eiK6MdQ');       // Change this to your cPanel database password

// 2. Security Configurations (Must match Kotlin client)
define('HARDCODED_MAIN_KEY',      'sxguGsnhZ0qVHWWZuRscY0rBA23kxA4M');
define('HARDCODED_MASTER_SECRET', '9pMuRqBvNnNlKQATwhV2oWAQazjlsEiP');
define('GOOGLE_PACKAGE_NAME',     'com.colorlinker.puzzle'); // Kotlin app Package ID
define('REQUEST_EXPIRY_MS',       60000); // 60 seconds request expiry window

// 3. PDO DB Instance Getter (Lightweight & High Performance)
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            $pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            die(json_encode(['success' => false, 'error' => 'DB connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// 4. Global response helper
function respond($success, $error = null, $data = []) {
    header('Content-Type: application/json');
    $r = ['success' => $success];
    if ($error) $r['error'] = $error;
    if (!empty($data)) $r = array_merge($r, $data);
    echo json_encode($r, JSON_PRETTY_PRINT); exit;
}

// 5. Security Log database recorder
function logSecurity($deviceId, $eventType, $details = '') {
    try {
        $db = getDB(); $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $db->prepare("INSERT INTO security_logs (device_id, ip_address, event_type, details) VALUES (?, ?, ?, ?)")
           ->execute([$deviceId, $ip, $eventType, $details]);
    } catch (Exception $e) {}
}

// 6. Cryptographic random string generator
function generateRandomKey($length = 32) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $key = '';
    for ($i = 0; $i < $length; $i++) $key .= $chars[random_int(0, strlen($chars) - 1)];
    return $key;
}

// 7. Secure Client Timezone Detection from IP country headers and device locale
function getSecureClientTimezone($deviceInfoJson) {
    // 1. Detect from server GeoIP headers (Cloudflare, LiteSpeed, Apache, etc.)
    $countryCode = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? $_SERVER['HTTP_X_COUNTRY_CODE'] ?? $_SERVER['GEOIP_COUNTRY_CODE'] ?? '';
    if (!empty($countryCode) && $countryCode !== 'XX') {
        $tz = getCountryTimezone($countryCode);
        if ($tz) return $tz;
    }
    
    // 2. Fallback to hardcoded device locale (already in device_info payload)
    if (!empty($deviceInfoJson)) {
        $di = json_decode($deviceInfoJson, true);
        if ($di && isset($di['gp']['locale'])) {
            $locale = str_replace('-', '_', $di['gp']['locale']);
            $parts = explode('_', $locale);
            $country = strtoupper(end($parts));
            if (strlen($country) === 2) {
                $tz = getCountryTimezone($country);
                if ($tz) return $tz;
            }
        }
    }
    
    // Default fallback (India)
    return 'Asia/Kolkata';
}

// 8. Map ISO Country Code to its standard timezone
function getCountryTimezone($countryCode) {
    if (empty($countryCode) || strlen($countryCode) !== 2) {
        return null;
    }
    
    $countryCode = strtoupper($countryCode);
    
    // Map standard timezones for major countries with multiple timezones
    $majorTimezones = [
        'US' => 'America/New_York',
        'CA' => 'America/Toronto',
        'AU' => 'Australia/Sydney',
        'BR' => 'America/Sao_Paulo',
        'RU' => 'Europe/Moscow',
    ];
    
    if (isset($majorTimezones[$countryCode])) {
        return $majorTimezones[$countryCode];
    }
    
    // Dynamic lookup for all other countries in the world (200+ countries)
    try {
        $timezones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $countryCode);
        if (!empty($timezones)) {
            return $timezones[0];
        }
    } catch (Exception $e) {
        // Fail silently
    }
    
    return null;
}

// 9. Fetch Live Payout Ticker Data for Marquee
function getLivePayoutTickerData($db) {
    try {
        $tsStmt = $db->query("SELECT ticker_enabled, ticker_show_home, ticker_show_register, ticker_show_dashboard, ticker_speed, ticker_custom_items FROM tester_settings WHERE id = 1 LIMIT 1");
        $t = $tsStmt ? $tsStmt->fetch(PDO::FETCH_ASSOC) : null;
        
        $enabled = $t ? intval($t['ticker_enabled']) === 1 : true;
        $showHome = $t ? intval($t['ticker_show_home']) === 1 : true;
        $showRegister = $t ? intval($t['ticker_show_register']) === 1 : true;
        $showDashboard = $t ? intval($t['ticker_show_dashboard']) === 1 : true;
        $speed = $t ? intval($t['ticker_speed']) : 45;
        
        $items = [];
        
        // Fetch real sent payout claims
        $claimsStmt = $db->query("
            SELECT tu.name, trc.amount, trc.payment_method, trc.updated_at, trc.created_at
            FROM tester_reward_claims trc
            LEFT JOIN tester_users tu ON tu.device_id = trc.device_id
            WHERE trc.status = 'sent'
            ORDER BY trc.updated_at DESC, trc.id DESC
            LIMIT 15
        ");
        if ($claimsStmt) {
            while ($c = $claimsStmt->fetch(PDO::FETCH_ASSOC)) {
                $rawName = trim($c['name'] ?? 'Tester');
                $parts = explode(' ', $rawName);
                $maskedName = (count($parts) > 1) 
                    ? $parts[0] . ' ' . substr($parts[1], 0, 1) . '.' 
                    : (strlen($rawName) > 3 ? substr($rawName, 0, 3) . '***' : $rawName);
                
                $timeAgo = 'Recently';
                $dt = $c['updated_at'] ?: $c['created_at'];
                if ($dt) {
                    $diff = time() - strtotime($dt);
                    if ($diff < 3600) $timeAgo = max(1, round($diff / 60)) . 'm ago';
                    elseif ($diff < 86400) $timeAgo = round($diff / 3600) . 'h ago';
                    else $timeAgo = round($diff / 86400) . 'd ago';
                }
                
                $items[] = [
                    'name' => $maskedName,
                    'amount' => intval($c['amount']),
                    'method' => $c['payment_method'] ?: 'UPI',
                    'time' => $timeAgo
                ];
            }
        }
        
        // Parse custom items from admin setting if configured
        if (!empty($t['ticker_custom_items'])) {
            $lines = explode("\n", str_replace("\r", "", $t['ticker_custom_items']));
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                $p = array_map('trim', explode('|', $line));
                if (count($p) >= 2) {
                    $items[] = [
                        'name' => $p[0],
                        'amount' => intval($p[1]),
                        'method' => $p[2] ?? 'UPI',
                        'time' => $p[3] ?? 'Just now'
                    ];
                }
            }
        }
        
        // Fallback default demo items if empty
        if (empty($items)) {
            $items = [
                ['name' => 'Aarav S.', 'amount' => 150, 'method' => 'UPI', 'time' => '2m ago'],
                ['name' => 'Priya M.', 'amount' => 150, 'method' => 'Paytm', 'time' => '5m ago'],
                ['name' => 'Rahul K.', 'amount' => 150, 'method' => 'Google Play', 'time' => '8m ago'],
                ['name' => 'Sneha D.', 'amount' => 150, 'method' => 'Amazon Pay', 'time' => '12m ago'],
                ['name' => 'Vikram R.', 'amount' => 150, 'method' => 'UPI', 'time' => '15m ago'],
                ['name' => 'Ananya G.', 'amount' => 150, 'method' => 'Paytm', 'time' => '18m ago'],
                ['name' => 'Rohan P.', 'amount' => 150, 'method' => 'UPI', 'time' => '24m ago'],
                ['name' => 'Neha S.', 'amount' => 150, 'method' => 'Google Play', 'time' => '30m ago']
            ];
        }
        
        return [
            'enabled' => $enabled,
            'show_home' => $showHome,
            'show_register' => $showRegister,
            'show_dashboard' => $showDashboard,
            'speed' => $speed,
            'items' => $items
        ];
    } catch (Exception $e) {
        return [
            'enabled' => true,
            'show_home' => true,
            'show_register' => true,
            'show_dashboard' => true,
            'speed' => 45,
            'items' => []
        ];
    }
}
?>
