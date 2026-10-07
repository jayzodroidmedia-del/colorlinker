<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

// 1. Database Connection
$db = getDB();

$message = '';
$error = '';
$loginError = '';

function isVoucherMethod($method) {
    if (empty($method)) return false;
    $m = strtolower(trim($method));
    return (
        strpos($m, 'google') !== false ||
        strpos($m, 'play') !== false ||
        strpos($m, 'amazon') !== false ||
        strpos($m, 'voucher') !== false ||
        strpos($m, 'code') !== false ||
        strpos($m, 'gift') !== false ||
        strpos($m, 'card') !== false ||
        strpos($m, 'redeem') !== false ||
        strpos($m, 'flipkart') !== false ||
        strpos($m, 'apple') !== false
    );
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header("Location: admin.php");
    exit;
}

// Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'admin_login') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $loginError = "Please enter both username and password.";
    } else {
        try {
            // Ensure admin table exists and has default admin if empty
            $cntAdmin = $db->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
            if (intval($cntAdmin) === 0) {
                $defaultHash = password_hash('admin123', PASSWORD_BCRYPT);
                $stmtInitAdmin = $db->prepare("INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, 'admin')");
                $stmtInitAdmin->execute(['admin', $defaultHash]);
            }

            $stmt = $db->prepare("SELECT id, username, password_hash, role FROM admin_users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_user_id'] = $user['id'];
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_role'] = $user['role'];

                $db->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                header("Location: admin.php");
                exit;
            } else {
                $loginError = "Invalid username or password. Default username: <strong>admin</strong> | password: <strong>admin123</strong>";
            }
        } catch (Exception $e) {
            $loginError = "Login Database Error: " . $e->getMessage();
        }
    }
}

// Check Authentication Session
if (empty($_SESSION['admin_logged_in'])) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipecraze • Admin Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #090d16;
            --bg-card: #131d33;
            --border: #1e293b;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-glow: rgba(99, 102, 241, 0.35);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.2);
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body {
            background: radial-gradient(circle at top, #1e1b4b 0%, #090d16 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--text-main);
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: rgba(19, 29, 51, 0.75);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 36px 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 40px var(--primary-glow);
        }
        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }
        .brand-logo {
            width: 58px;
            height: 58px;
            background: linear-gradient(135deg, #6366f1, #a855f7);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.5);
            margin-bottom: 14px;
        }
        .brand-title {
            font-size: 22px;
            font-weight: 800;
            color: #fff;
            letter-spacing: -0.5px;
        }
        .brand-desc {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-muted);
        }
        .input-box {
            position: relative;
        }
        .form-control {
            width: 100%;
            background: #0b1220;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 16px;
            color: #fff;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
        }
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 13px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.35);
            margin-top: 8px;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #4f46e5, #4338ca);
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(99, 102, 241, 0.5);
        }
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .security-badge {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: var(--text-dim);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="brand-header">
            <div class="brand-logo">🏹</div>
            <h1 class="brand-title">Pipecraze Studio</h1>
            <p class="brand-desc">Secure Management & Payout Control Center</p>
        </div>

        <?php if (!empty($loginError)): ?>
            <div class="alert-error">
                <span>⚠️</span>
                <span><?= strip_tags($loginError, '<strong><b><i><code>') ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="admin.php" autocomplete="off">
            <input type="hidden" name="action" value="admin_login">
            <div class="form-group">
                <label class="form-label">Username</label>
                <div class="input-box">
                    <input type="text" name="username" class="form-control" placeholder="Enter admin username" required autofocus value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-box">
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn-login">🔐 Sign In to Control Center</button>
        </form>

        <div class="security-badge">
            <span>🛡️</span>
            <span>Bcrypt Hashed & Session Protected</span>
        </div>
    </div>
</body>
</html>
<?php
    exit;
}

// 2. Form Submission Handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'get_user_full_audit') {
            header('Content-Type: application/json');
            $deviceId = trim($_POST['device_id'] ?? '');
            if (empty($deviceId)) {
                echo json_encode(['success' => false, 'error' => 'Device ID is required']);
                exit;
            }

            try {
                // 1. Basic User Data
                $userStmt = $db->prepare("SELECT u.*, dk.pkg_name, dk.registration_ip, dk.is_active as key_is_active, dk.created_at as dk_created, dk.last_request_at, dk.referrer as dk_referrer, dk.rewardbro_uid as dk_uid, dk.gid as dk_gid FROM users u LEFT JOIN device_keys dk ON u.device_id = dk.device_id WHERE u.device_id = ? LIMIT 1");
                $userStmt->execute([$deviceId]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);

                // 2. Tester User Details
                $testerStmt = $db->prepare("SELECT * FROM tester_users WHERE device_id = ? LIMIT 1");
                $testerStmt->execute([$deviceId]);
                $tester = $testerStmt->fetch(PDO::FETCH_ASSOC);

                // 3. Campaign & Attribution
                $campStmt = $db->prepare("SELECT * FROM user_campaigns WHERE device_id = ? LIMIT 1");
                $campStmt->execute([$deviceId]);
                $campaign = $campStmt->fetch(PDO::FETCH_ASSOC);

                // 4. Tester Daily Progress & QA Reviews
                $progStmt = $db->prepare("SELECT tdp.*, tdc.title as day_title, tdc.required_levels as cfg_req_levels FROM tester_daily_progress tdp LEFT JOIN tester_day_configs tdc ON tdp.day_number = tdc.day_number WHERE tdp.device_id = ? ORDER BY tdp.cycle ASC, tdp.day_number ASC");
                $progStmt->execute([$deviceId]);
                $dailyProgress = $progStmt->fetchAll(PDO::FETCH_ASSOC);

                // 5. Level Progression & History stats
                $lvlCountStmt = $db->prepare("SELECT COUNT(*) as total_records, MIN(created_at) as first_level_time, MAX(created_at) as last_level_time, COUNT(DISTINCT level) as unique_levels FROM level_history WHERE device_id = ?");
                $lvlCountStmt->execute([$deviceId]);
                $levelStats = $lvlCountStmt->fetch(PDO::FETCH_ASSOC);

                // Recent 50 level history records
                $lvlRecentStmt = $db->prepare("SELECT level, created_at, ip_address FROM level_history WHERE device_id = ? ORDER BY level DESC LIMIT 50");
                $lvlRecentStmt->execute([$deviceId]);
                $recentLevels = $lvlRecentStmt->fetchAll(PDO::FETCH_ASSOC);

                // 6. Security Logs & Bypass Logs
                $secStmt = $db->prepare("SELECT * FROM security_logs WHERE device_id = ? ORDER BY id DESC LIMIT 30");
                $secStmt->execute([$deviceId]);
                $secLogs = $secStmt->fetchAll(PDO::FETCH_ASSOC);

                $bypassStmt = $db->prepare("SELECT * FROM level_bypass_logs WHERE device_id = ? ORDER BY id DESC LIMIT 30");
                $bypassStmt->execute([$deviceId]);
                $bypassLogs = $bypassStmt->fetchAll(PDO::FETCH_ASSOC);

                // 7. Postback History
                $pbStmt = $db->prepare("SELECT * FROM postback_history WHERE device_id = ? ORDER BY id DESC LIMIT 30");
                $pbStmt->execute([$deviceId]);
                $postbacks = $pbStmt->fetchAll(PDO::FETCH_ASSOC);

                // 8. Payout History (Tester claims + Milestone rewards)
                $testerClaims = [];
                try {
                    $tClaimStmt = $db->prepare("SELECT id, 'tester' as type, cycle, amount, payment_method, account_details, COALESCE(voucher_code, '') as voucher_code, COALESCE(admin_notes, '') as admin_notes, status, created_at FROM tester_reward_claims WHERE device_id = ? ORDER BY id DESC");
                    $tClaimStmt->execute([$deviceId]);
                    $testerClaims = $tClaimStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Exception $e) {
                    try {
                        $tClaimStmt = $db->prepare("SELECT id, 'tester' as type, cycle, amount, payment_method, account_details, '' as voucher_code, '' as admin_notes, status, created_at FROM tester_reward_claims WHERE device_id = ? ORDER BY id DESC");
                        $tClaimStmt->execute([$deviceId]);
                        $testerClaims = $tClaimStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (Exception $e2) {}
                }

                $milestoneClaims = [];
                try {
                    $mRewardStmt = $db->prepare("SELECT id, 'milestone' as type, 1 as cycle, amount, method as payment_method, account as account_details, COALESCE(voucher_code, '') as voucher_code, COALESCE(admin_notes, '') as admin_notes, status, created_at FROM rewards WHERE device_id = ? ORDER BY id DESC");
                    $mRewardStmt->execute([$deviceId]);
                    $milestoneClaims = $mRewardStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Exception $e) {
                    try {
                        $mRewardStmt = $db->prepare("SELECT id, 'milestone' as type, 1 as cycle, amount, payment_method, account_details, COALESCE(voucher_code, '') as voucher_code, COALESCE(admin_notes, '') as admin_notes, status, created_at FROM rewards WHERE device_id = ? ORDER BY id DESC");
                        $mRewardStmt->execute([$deviceId]);
                        $milestoneClaims = $mRewardStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                    } catch (Exception $e2) {}
                }

                $allClaims = array_merge($testerClaims, $milestoneClaims);
                usort($allClaims, function($a, $b) {
                    return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
                });

                // 9. Trust & Fraud Score Evaluation
                $trustScore = 100;
                $flags = [];
                $positives = [];

                $isBanned = ($user && intval($user['is_banned'] ?? 0) === 1);
                if ($isBanned) {
                    $trustScore = 0;
                    $flags[] = "❌ Device is currently marked BANNED in database";
                } else {
                    $positives[] = "✅ Device status is Active (No active bans)";
                }

                if (count($bypassLogs) > 0) {
                    $trustScore -= 50;
                    $flags[] = "⚠️ Level Jump / Speed Bypass detected (" . count($bypassLogs) . " bypass attempt logged)";
                } else {
                    $positives[] = "✅ Zero Level Jump / Speed Bypass violations";
                }

                $failedSecCount = 0;
                foreach ($secLogs as $sl) {
                    $ev = strtolower($sl['event_type'] ?? '');
                    if (strpos($ev, 'device_failed') !== false || strpos($ev, 'device_check_failed') !== false || strpos($ev, 'emulator') !== false) {
                        $failedSecCount++;
                    }
                }
                if ($failedSecCount > 0) {
                    $trustScore -= 30;
                    $flags[] = "⚠️ Hardware / Emulator / Signature check failed in security logs ($failedSecCount times)";
                } else {
                    $positives[] = "✅ Real Device hardware integrity passed";
                }

                $currentDbLevel = intval($user['current_level'] ?? 1);
                $uniqueLevels = intval($levelStats['unique_levels'] ?? 0);
                if ($currentDbLevel > 2 && $uniqueLevels < ($currentDbLevel - 2)) {
                    $trustScore -= 20;
                    $flags[] = "⚠️ Incomplete level history log: DB Level is $currentDbLevel but recorded level checkpoints are only $uniqueLevels";
                } elseif ($uniqueLevels > 0) {
                    $positives[] = "✅ Consistent sequential level completion logged ($uniqueLevels unique levels)";
                }

                // Check 7-day tester completion speed
                if ($tester && count($dailyProgress) >= 7) {
                    $firstDayTime = strtotime($dailyProgress[0]['created_at'] ?? 'now');
                    $lastDayTime = strtotime(end($dailyProgress)['completed_at'] ?? 'now');
                    $hoursSpan = ($lastDayTime - $firstDayTime) / 3600;
                    if ($hoursSpan < 24 && count($dailyProgress) >= 7) {
                        $trustScore -= 25;
                        $flags[] = "⚠️ Suspicious speed: All 7 days cleared in only " . round($hoursSpan, 1) . " hours";
                    } else {
                        $positives[] = "✅ Authentic 7-day tester progression (Cleared over " . round(max(1, $hoursSpan / 24), 1) . " days)";
                    }
                }

                $trustScore = max(0, min(100, $trustScore));
                $trustGrade = 'A+ (Genuine User)';
                $trustColor = '#34d399';
                if ($trustScore < 40) {
                    $trustGrade = 'F (High Risk / Fraud Detected)';
                    $trustColor = '#f87171';
                } elseif ($trustScore < 75) {
                    $trustGrade = 'C (Caution / Suspicious Activity)';
                    $trustColor = '#fbbf24';
                }

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'device_id' => $deviceId,
                        'user' => $user ?: [
                            'device_id' => $deviceId,
                            'current_level' => 1,
                            'is_banned' => 0,
                            'created_at' => 'N/A'
                        ],
                        'tester' => $tester,
                        'campaign' => $campaign,
                        'daily_progress' => $dailyProgress,
                        'level_stats' => $levelStats,
                        'recent_levels' => $recentLevels,
                        'sec_logs' => $secLogs,
                        'bypass_logs' => $bypassLogs,
                        'postbacks' => $postbacks,
                        'claims' => $allClaims,
                        'trust' => [
                            'score' => $trustScore,
                            'grade' => $trustGrade,
                            'color' => $trustColor,
                            'flags' => $flags,
                            'positives' => $positives
                        ]
                    ]
                ], JSON_PRETTY_PRINT);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        } elseif ($action === 'ban_user_and_device') {
            header('Content-Type: application/json');
            $deviceId = trim($_POST['device_id'] ?? '');
            $reason = trim($_POST['reason'] ?? 'Banned by Admin from User Audit Studio');

            if (empty($deviceId)) {
                echo json_encode(['success' => false, 'error' => 'Device ID is required']);
                exit;
            }

            try {
                $db->prepare("UPDATE users SET is_banned = 1 WHERE device_id = ?")->execute([$deviceId]);
                $db->prepare("UPDATE device_keys SET is_active = 0 WHERE device_id = ?")->execute([$deviceId]);
                $db->prepare("UPDATE tester_reward_claims SET status = 'rejected', admin_notes = ? WHERE device_id = ? AND status = 'pending'")->execute([$reason, $deviceId]);
                $db->prepare("UPDATE rewards SET status = 'rejected', admin_notes = ? WHERE device_id = ? AND status = 'pending'")->execute([$reason, $deviceId]);
                logSecurity($deviceId, 'admin_manual_ban', $reason);
                echo json_encode(['success' => true, 'message' => "Device $deviceId successfully banned and pending payouts rejected."]);
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        } elseif ($action === 'auto_complete_campaign') {
            // Feature 1: Auto-Complete Campaign & Fire Postbacks (One-Click)
            $deviceId = trim($_POST['device_id'] ?? '');
            $startTime = trim($_POST['start_time'] ?? '');

            if (empty($deviceId) || empty($startTime)) {
                throw new Exception("Device ID and Completion Date/Time are required.");
            }

            // 1. Get campaign
            $campStmt = $db->prepare("SELECT rewardbro_uid, offer_id, event_id, created_at FROM user_campaigns WHERE device_id = ?");
            $campStmt->execute([$deviceId]);
            $campaign = $campStmt->fetch(PDO::FETCH_ASSOC);

            if (!$campaign) {
                throw new Exception("No campaign found for device ID: $deviceId. The device must be registered and attributed first.");
            }

            $rewardbroUid = $campaign['rewardbro_uid'];
            $offerId = $campaign['offer_id'];
            $eventId = $campaign['event_id'];

            // 2. Fetch postback settings
            $settingsStmt = $db->query("SELECT secret_key, target_level, event_id, coin FROM postback_settings ORDER BY target_level ASC");
            $allSettings = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($allSettings)) {
                throw new Exception("No postback settings configured in database.");
            }

            $maxTargetLevel = 1;
            foreach ($allSettings as $s) {
                if (intval($s['target_level']) > $maxTargetLevel) {
                    $maxTargetLevel = intval($s['target_level']);
                }
            }

            $baseTimestamp = strtotime($startTime);
            $campaignStart = date('Y-m-d H:i:s', $baseTimestamp - 300);

            // 3. Update campaign created_at and set status to active
            $db->prepare("UPDATE user_campaigns SET created_at = ?, status = 'active' WHERE device_id = ?")->execute([$campaignStart, $deviceId]);

            // 4. Generate sequential Level History
            $db->prepare("DELETE FROM level_history WHERE device_id = ?")->execute([$deviceId]);
            $db->prepare("INSERT INTO level_history (device_id, level, ip_address, created_at) VALUES (?, 1, '127.0.0.1', ?)")
               ->execute([$deviceId, $campaignStart]);

            for ($lvl = 2; $lvl <= $maxTargetLevel; $lvl++) {
                $timeOffset = ($lvl - 2) * 120;
                $levelTime = date('Y-m-d H:i:s', $baseTimestamp + $timeOffset);
                $db->prepare("INSERT INTO level_history (device_id, level, ip_address, created_at) VALUES (?, ?, '127.0.0.1', ?)")
                   ->execute([$deviceId, $lvl, $levelTime]);
            }

            // 5. Update user current level
            $newCurrentLevel = $maxTargetLevel + 1;
            $db->prepare("INSERT INTO users (device_id, current_level) VALUES (?, ?) ON DUPLICATE KEY UPDATE current_level = VALUES(current_level)")
               ->execute([$deviceId, $newCurrentLevel]);

            // 6. Fire postbacks
            $postbackResults = [];
            foreach ($allSettings as $settings) {
                $targetLevel = intval($settings['target_level']);
                $secretKey = $settings['secret_key'];
                $settingsEventId = $settings['event_id'] ?? '';
                $coinVal = intval($settings['coin'] ?? 0);
                $finalEventId = !empty($settingsEventId) ? $settingsEventId : $eventId;

                $db->prepare("DELETE FROM postback_history WHERE device_id = ? AND level = ?")->execute([$deviceId, $targetLevel]);

                $postbackUrl = "https://app.rewardbro.in/cpa-postback?userId=" . urlencode($rewardbroUid) . 
                               "&appName=rewardbro" . 
                               "&offerId=" . urlencode($offerId) . 
                               "&secret=" . urlencode($secretKey) . 
                               "&eventId=" . urlencode($finalEventId);

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $postbackUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                $responseBody = curl_exec($ch);
                $responseStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($responseBody === false) {
                    $responseBody = 'cURL error: ' . curl_error($ch);
                    $responseStatus = 0;
                }
                curl_close($ch);

                $historyStmt = $db->prepare("INSERT INTO postback_history (device_id, rewardbro_uid, offer_id, event_id, level, postback_url, response_status, response_body, coin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $historyStmt->execute([$deviceId, $rewardbroUid, $offerId, $finalEventId, $targetLevel, $postbackUrl, $responseStatus, $responseBody, $coinVal]);

                $db->prepare("INSERT INTO security_logs (device_id, ip_address, event_type, details) VALUES (?, '127.0.0.1', 'postback_triggered', ?)")
                   ->execute([$deviceId, "Admin auto-triggered postback for level $targetLevel (status $responseStatus)"]);

                if ($targetLevel === $maxTargetLevel && intval($responseStatus) >= 200 && intval($responseStatus) < 300) {
                    $db->prepare("UPDATE user_campaigns SET status = 'completed' WHERE device_id = ? AND offer_id = ?")->execute([$deviceId, $offerId]);
                }

                $postbackResults[] = [
                    'level' => $targetLevel,
                    'status' => $responseStatus,
                    'response' => htmlspecialchars(substr($responseBody, 0, 150))
                ];
            }

            $message = "<strong>Auto-Complete Successful!</strong><br>";
            $message .= "• Generated history up to Level $maxTargetLevel for device: <code>$deviceId</code>.<br>";
            $message .= "• Current Level updated to <strong>$newCurrentLevel</strong>.<br>";
            $message .= "• Fired postbacks sequentially:<br>";
            foreach ($postbackResults as $res) {
                $statusColor = ($res['status'] >= 200 && $res['status'] < 300) ? '#10b981' : '#ef4444';
                $message .= "&nbsp;&nbsp;→ Level <strong>{$res['level']}</strong>: Status <strong style='color:$statusColor;'>{$res['status']}</strong> (Response: <code>{$res['response']}</code>)<br>";
            }

        } elseif ($action === 'generate_history') {
            $deviceId = trim($_POST['device_id'] ?? '');
            $startLevel = intval($_POST['start_level'] ?? 1);
            $targetLevel = intval($_POST['target_level'] ?? 0);
            $startTime = trim($_POST['start_time'] ?? '');
            $customCurrentLevel = isset($_POST['custom_current_level']) && $_POST['custom_current_level'] !== '' ? intval($_POST['custom_current_level']) : null;

            if (empty($deviceId) || $targetLevel <= 0 || $startLevel <= 0 || empty($startTime)) {
                throw new Exception("All fields are required for level history generation.");
            }
            if ($startLevel > $targetLevel) {
                throw new Exception("Start level cannot be greater than target level.");
            }

            $db->prepare("DELETE FROM level_history WHERE device_id = ? AND level >= ?")->execute([$deviceId, $startLevel]);
            $baseTimestamp = strtotime($startTime);

            for ($lvl = $startLevel; $lvl <= $targetLevel; $lvl++) {
                $timeOffset = ($lvl - $startLevel) * 120;
                $levelTime = date('Y-m-d H:i:s', $baseTimestamp + $timeOffset);
                $db->prepare("INSERT INTO level_history (device_id, level, ip_address, created_at) VALUES (?, ?, '127.0.0.1', ?)")
                   ->execute([$deviceId, $lvl, $levelTime]);
            }

            $newCurrentLevel = ($customCurrentLevel !== null) ? $customCurrentLevel : ($targetLevel + 1);
            $db->prepare("INSERT INTO users (device_id, current_level) VALUES (?, ?) ON DUPLICATE KEY UPDATE current_level = VALUES(current_level)")
               ->execute([$deviceId, $newCurrentLevel]);

            $message = "Generated level history from Level $startLevel to $targetLevel for device <code>$deviceId</code> (Current level: <strong>$newCurrentLevel</strong>).";

        } elseif ($action === 'delete_device') {
            $deviceId = trim($_POST['device_id'] ?? '');
            if (empty($deviceId)) {
                throw new Exception("Device ID is required for deletion.");
            }

            $rewardbroUid = null;
            $gaid = null;
            $stmt = $db->prepare("SELECT rewardbro_uid, gid FROM device_keys WHERE device_id = ?");
            $stmt->execute([$deviceId]);
            $dk = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($dk) {
                $rewardbroUid = $dk['rewardbro_uid'];
                $gaid = $dk['gid'];
            }

            $db->prepare("DELETE FROM user_campaigns WHERE device_id = ?")->execute([$deviceId]);
            $db->prepare("DELETE FROM rewards WHERE device_id = ?")->execute([$deviceId]);
            $db->prepare("DELETE FROM users WHERE device_id = ?")->execute([$deviceId]);
            $db->prepare("DELETE FROM device_keys WHERE device_id = ?")->execute([$deviceId]);
            $db->prepare("DELETE FROM level_history WHERE device_id = ?")->execute([$deviceId]);
            $db->prepare("DELETE FROM level_bypass_logs WHERE device_id = ?")->execute([$deviceId]);
            $db->prepare("DELETE FROM postback_history WHERE device_id = ?")->execute([$deviceId]);
            $db->prepare("DELETE FROM security_logs WHERE device_id = ?")->execute([$deviceId]);

            if (!empty($rewardbroUid) || !empty($gaid)) {
                $sql = "DELETE FROM campaign_clicks WHERE 1=0";
                $params = [];
                if (!empty($rewardbroUid)) { $sql .= " OR rewardbro_uid = ?"; $params[] = $rewardbroUid; }
                if (!empty($gaid)) { $sql .= " OR gaid = ?"; $params[] = $gaid; }
                $db->prepare($sql)->execute($params);
            }

            $message = "Completely wiped all records for device: <code>$deviceId</code>.";

        } elseif ($action === 'edit_campaign') {
            $deviceId = trim($_POST['device_id'] ?? '');
            $status = $_POST['status'] ?? '';
            $createdAt = $_POST['created_at'] ?? '';
            $updatedAt = $_POST['updated_at'] ?? '';
            $currentLevel = $_POST['current_level'] ?? '';

            if (empty($deviceId)) {
                throw new Exception("Device ID is required.");
            }

            if ($status !== '' || $createdAt !== '' || $updatedAt !== '') {
                $fields = [];
                $params = [];
                if ($status !== '') { $fields[] = "status = ?"; $params[] = $status; }
                if ($createdAt !== '') { $fields[] = "created_at = ?"; $params[] = $createdAt; }
                if ($updatedAt !== '') { $fields[] = "updated_at = ?"; $params[] = $updatedAt; }
                
                if (!empty($fields)) {
                    $sql = "UPDATE user_campaigns SET " . implode(', ', $fields) . " WHERE device_id = ?";
                    $params[] = $deviceId;
                    $db->prepare($sql)->execute($params);
                }
            }

            if ($currentLevel !== '') {
                $db->prepare("UPDATE users SET current_level = ? WHERE device_id = ?")->execute([intval($currentLevel), $deviceId]);
            }

            $message = "Successfully updated campaign settings for device: <code>$deviceId</code>.";

        } elseif ($action === 'find_device_id') {
            $rewardbroUid = trim($_POST['rewardbro_uid'] ?? '');
            if (empty($rewardbroUid)) {
                throw new Exception("Rewardbro UID is required.");
            }

            $stmt = $db->prepare("
                SELECT DISTINCT device_id, 'device_keys' as source FROM device_keys WHERE rewardbro_uid = ?
                UNION
                SELECT DISTINCT device_id, 'users' as source FROM users WHERE rewardbro_uid = ?
                UNION
                SELECT DISTINCT device_id, 'user_campaigns' as source FROM user_campaigns WHERE rewardbro_uid = ?
            ");
            $stmt->execute([$rewardbroUid, $rewardbroUid, $rewardbroUid]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($results)) {
                $message = "No device ID found for Rewardbro UID: <strong>" . htmlspecialchars($rewardbroUid) . "</strong>";
            } else {
                $message = "Found Device ID(s) for UID <strong>" . htmlspecialchars($rewardbroUid) . "</strong>:<br>";
                foreach ($results as $row) {
                    $message .= "• <code>{$row['device_id']}</code> (Source: <em>{$row['source']}</em>)<br>";
                }
            }

        } elseif ($action === 'fix_stuck_user') {
            $deviceId = trim($_POST['device_id'] ?? '');
            if (empty($deviceId)) {
                throw new Exception("Device ID is required.");
            }

            $campStmt = $db->prepare("SELECT created_at FROM user_campaigns WHERE device_id = ? AND status = 'active'");
            $campStmt->execute([$deviceId]);
            $camp = $campStmt->fetch(PDO::FETCH_ASSOC);

            if (!$camp) {
                throw new Exception("No active campaign found for device ID: $deviceId");
            }

            $db->prepare("UPDATE users SET current_level = 1 WHERE device_id = ?")->execute([$deviceId]);
            $message = "Successfully fixed stuck device <code>$deviceId</code>: Current level set to 1. History preserved.";

        } elseif ($action === 'toggle_reward_setting') {
            $id = intval($_POST['id'] ?? 0);
            $newStatus = intval($_POST['status'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE reward_settings SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                $message = "Reward milestone ID $id status updated to: <strong>" . ($newStatus === 1 ? 'ACTIVE' : 'DISABLED') . "</strong>";
            }

        } elseif ($action === 'save_reward_setting') {
            $reqLevel = intval($_POST['required_level'] ?? 0);
            $rewardAmt = intval($_POST['reward_amount'] ?? 0);
            $msg = trim($_POST['message'] ?? '');
            $status = intval($_POST['status'] ?? 1);

            if ($reqLevel <= 0 || $rewardAmt <= 0) {
                throw new Exception("Required Level and Reward Amount must be greater than 0.");
            }

            $db->prepare("INSERT INTO reward_settings (required_level, reward_amount, message, status) VALUES (?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE reward_amount = VALUES(reward_amount), message = VALUES(message), status = VALUES(status)")
               ->execute([$reqLevel, $rewardAmt, $msg, $status]);
            $message = "Saved milestone reward for Level $reqLevel (Amount: ₹$rewardAmt).";

        } elseif ($action === 'delete_reward_setting') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM reward_settings WHERE id = ?")->execute([$id]);
                $message = "Deleted milestone reward ID: $id";
            }

        } elseif ($action === 'update_claim_status') {
            $claimId = intval($_POST['claim_id'] ?? 0);
            $newClaimStatus = trim($_POST['claim_status'] ?? '');
            if ($claimId > 0 && in_array($newClaimStatus, ['pending', 'approved', 'completed', 'rejected'])) {
                $db->prepare("UPDATE rewards SET status = ? WHERE id = ?")->execute([$newClaimStatus, $claimId]);
                $message = "Reward Claim #$claimId status updated to <strong>" . strtoupper($newClaimStatus) . "</strong>";
            }

        } elseif ($action === 'toggle_rewards_master') {
            $status = intval($_POST['status'] ?? 0);
            $db->prepare("UPDATE app_settings SET rewards_enabled = ? WHERE id = 1")->execute([$status]);
            $message = "Rewards section globally set to <strong>" . ($status === 1 ? 'ENABLED (VISIBLE)' : 'DISABLED (HIDDEN)') . "</strong>.";

        } elseif ($action === 'save_banner') {
            $id = intval($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $imageUrl = trim($_POST['image_url'] ?? '');
            $actionType = trim($_POST['action_type'] ?? 'tester_program');
            $actionValue = trim($_POST['action_value'] ?? '');
            $buttonText = trim($_POST['button_text'] ?? '');
            $sortOrder = intval($_POST['sort_order'] ?? 0);
            $status = intval($_POST['status'] ?? 1);

            if (empty($imageUrl)) {
                throw new Exception("Banner Image URL is required.");
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE app_banners SET title = ?, description = ?, image_url = ?, action_type = ?, action_value = ?, button_text = ?, sort_order = ?, status = ? WHERE id = ?");
                $stmt->execute([$title, $description, $imageUrl, $actionType, $actionValue, $buttonText, $sortOrder, $status, $id]);
                $message = "Banner #$id updated successfully.";
            } else {
                $stmt = $db->prepare("INSERT INTO app_banners (title, description, image_url, action_type, action_value, button_text, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $description, $imageUrl, $actionType, $actionValue, $buttonText, $sortOrder, $status]);
                $message = "New banner created successfully.";
            }

        } elseif ($action === 'delete_banner') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM app_banners WHERE id = ?")->execute([$id]);
                $message = "Banner #$id deleted successfully.";
            }

        } elseif ($action === 'toggle_banner_status') {
            $id = intval($_POST['id'] ?? 0);
            $status = intval($_POST['status'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE app_banners SET status = ? WHERE id = ?")->execute([$status, $id]);
                $message = "Banner #$id status set to " . ($status === 1 ? 'Active' : 'Inactive') . ".";
            }

        } elseif ($action === 'save_topon_settings') {
            $toponAppId = trim($_POST['topon_app_id'] ?? '');
            $toponAppKey = trim($_POST['topon_app_key'] ?? '');
            $toponSplashId = trim($_POST['topon_splash_id'] ?? '');
            $toponInterId = trim($_POST['topon_interstitial_id'] ?? '');
            $toponRewardId = trim($_POST['topon_rewarded_id'] ?? '');
            $toponNativeId = trim($_POST['topon_native_id'] ?? '');
            $onesignalAppId = trim($_POST['onesignal_app_id'] ?? '');
            $onesignalRestApiKey = trim($_POST['onesignal_rest_api_key'] ?? '');
            $nativeEnabled = intval($_POST['native_enabled'] ?? 0);

            // Ensure column types are wide enough (TEXT for API key and VARCHAR(255) for App ID)
            try {
                $db->exec("ALTER TABLE `app_settings` MODIFY COLUMN `onesignal_rest_api_key` TEXT NULL");
                $db->exec("ALTER TABLE `app_settings` MODIFY COLUMN `onesignal_app_id` VARCHAR(255) NOT NULL DEFAULT ''");
            } catch (Exception $e) {}

            $stmt = $db->prepare("UPDATE app_settings SET 
                topon_app_id = ?, 
                topon_app_key = ?, 
                topon_splash_id = ?, 
                topon_interstitial_id = ?, 
                topon_rewarded_id = ?, 
                topon_native_id = ?,
                onesignal_app_id = ?,
                onesignal_rest_api_key = ?,
                native_enabled = ?
                WHERE id = 1");
            $stmt->execute([$toponAppId, $toponAppKey, $toponSplashId, $toponInterId, $toponRewardId, $toponNativeId, $onesignalAppId, $onesignalRestApiKey, $nativeEnabled]);
            $message = "TopOn Placement IDs & OneSignal credentials updated successfully!";

        } elseif ($action === 'send_push_notification') {
            $title = trim($_POST['title'] ?? '');
            $messageText = trim($_POST['message'] ?? '');
            $imageUrl = trim($_POST['image_url'] ?? '');
            $actionType = trim($_POST['action_type'] ?? 'open_app');
            $actionValue = trim($_POST['action_value'] ?? '');
            $buttonText = trim($_POST['button_text'] ?? '');

            if (empty($title) || empty($messageText)) {
                throw new Exception("Notification Title and Message cannot be empty.");
            }

            // Fetch OneSignal Credentials from app_settings
            $credStmt = $db->query("SELECT onesignal_app_id, onesignal_rest_api_key FROM app_settings WHERE id = 1 LIMIT 1");
            $creds = $credStmt ? $credStmt->fetch(PDO::FETCH_ASSOC) : null;
            $appId = $creds['onesignal_app_id'] ?? '';
            $restApiKey = $creds['onesignal_rest_api_key'] ?? '';

            if (empty($appId) || empty($restApiKey)) {
                throw new Exception("OneSignal App ID or REST API Key is missing. Please configure them in the OneSignal Settings card below.");
            }

            // Prepare OneSignal Payload
            $payload = [
                'app_id' => $appId,
                'included_segments' => ['Total Subscriptions'],
                'headings' => ['en' => $title],
                'contents' => ['en' => $messageText],
                'data' => [
                    'action_type' => $actionType,
                    'action_value' => $actionValue
                ]
            ];

            if (!empty($imageUrl)) {
                $payload['big_picture'] = $imageUrl;
                $payload['large_icon'] = $imageUrl;
                $payload['chrome_web_image'] = $imageUrl;
            }

            if (!empty($buttonText)) {
                $payload['buttons'] = [
                    [
                        'id' => 'action_btn_1',
                        'text' => $buttonText
                    ]
                ];
            }

            if ($actionType === 'open_url' && !empty($actionValue)) {
                $payload['url'] = $actionValue;
            }

            $ch = curl_init('https://onesignal.com/api/v1/notifications');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Basic ' . $restApiKey
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            $resData = json_decode($response, true);
            $onesignalId = $resData['id'] ?? '';
            $recipients = intval($resData['recipients'] ?? 0);
            $status = ($httpCode >= 200 && $httpCode < 300 && !empty($onesignalId)) ? 'sent' : 'failed';

            if (!empty($resData['errors'])) {
                $status = 'failed';
            }

            // Save to push_notifications table
            $insStmt = $db->prepare("INSERT INTO push_notifications (title, message, image_url, action_type, action_value, button_text, onesignal_id, recipients_count, status, response_raw) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insStmt->execute([
                $title,
                $messageText,
                $imageUrl,
                $actionType,
                $actionValue,
                $buttonText,
                $onesignalId,
                $recipients,
                $status,
                $response ?: $curlErr
            ]);

            if ($status === 'sent') {
                $message = "🎉 <strong>Push Notification Broadcasted Successfully!</strong> (Recipients: <strong>$recipients</strong>, Notification ID: <code>$onesignalId</code>)";
            } else {
                $errDetails = !empty($resData['errors']) ? (is_array($resData['errors']) ? implode(', ', $resData['errors']) : $resData['errors']) : ($curlErr ?: "HTTP Status $httpCode");
                throw new Exception("OneSignal Error: " . $errDetails);
            }

        } elseif ($action === 'delete_push_notification') {
            $notifId = intval($_POST['notif_id'] ?? 0);
            if ($notifId > 0) {
                $db->prepare("DELETE FROM push_notifications WHERE id = ?")->execute([$notifId]);
                $message = "Notification record #$notifId deleted.";
            }

        } elseif ($action === 'save_ad_screen_controls') {
            $adControlsInput = $_POST['ad_types'] ?? [];
            if (is_array($adControlsInput)) {
                $stmtUpdate = $db->prepare("UPDATE ad_controls SET ad_type = ? WHERE screen_key = ?");
                foreach ($adControlsInput as $sKey => $sType) {
                    $stmtUpdate->execute([trim($sType), trim($sKey)]);
                }
            }
            $message = "Ad Control triggers for all screens updated successfully!";

        } elseif ($action === 'save_tester_settings') {
            $instant = intval($_POST['instant_approval'] ?? 0);
            $rewardAmt = intval($_POST['reward_amount'] ?? 150);
            $totalDays = intval($_POST['total_days'] ?? 7);
            $isActive = intval($_POST['is_active'] ?? 1);
            $badgeText = trim($_POST['badge_text'] ?? 'EARN ₹150 GUARANTEED');
            $badgeColor = trim($_POST['badge_color'] ?? '#06D6A0');
            $title = trim($_POST['title'] ?? 'Join the 7-Day Testing Team');
            $description = trim($_POST['description'] ?? '');
            $perk1Title = trim($_POST['perk1_title'] ?? '10 Levels/Day');
            $perk1Subtitle = trim($_POST['perk1_subtitle'] ?? 'Daily Target');
            $perk2Title = trim($_POST['perk2_title'] ?? 'Bug Reports');
            $perk2Subtitle = trim($_POST['perk2_subtitle'] ?? 'Quick Review');
            $perk3Title = trim($_POST['perk3_title'] ?? 'Instant Cash');
            $perk3Subtitle = trim($_POST['perk3_subtitle'] ?? '₹150 Reward');
            $tickerEnabled = intval($_POST['ticker_enabled'] ?? 0);
            $tickerShowHome = intval($_POST['ticker_show_home'] ?? 0);
            $tickerShowRegister = intval($_POST['ticker_show_register'] ?? 0);
            $tickerShowDashboard = intval($_POST['ticker_show_dashboard'] ?? 0);
            $tickerSpeed = intval($_POST['ticker_speed'] ?? 25);
            $tickerCustomItems = trim($_POST['ticker_custom_items'] ?? '');

            $db->prepare("INSERT INTO tester_settings (id, instant_approval, total_days, reward_amount, is_active, badge_text, badge_color, title, description, perk1_title, perk1_subtitle, perk2_title, perk2_subtitle, perk3_title, perk3_subtitle, ticker_enabled, ticker_show_home, ticker_show_register, ticker_show_dashboard, ticker_speed, ticker_custom_items) 
                          VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                            instant_approval = VALUES(instant_approval),
                            total_days = VALUES(total_days),
                            reward_amount = VALUES(reward_amount),
                            is_active = VALUES(is_active),
                            badge_text = VALUES(badge_text),
                            badge_color = VALUES(badge_color),
                            title = VALUES(title),
                            description = VALUES(description),
                            perk1_title = VALUES(perk1_title),
                            perk1_subtitle = VALUES(perk1_subtitle),
                            perk2_title = VALUES(perk2_title),
                            perk2_subtitle = VALUES(perk2_subtitle),
                            perk3_title = VALUES(perk3_title),
                            perk3_subtitle = VALUES(perk3_subtitle),
                            ticker_enabled = VALUES(ticker_enabled),
                            ticker_show_home = VALUES(ticker_show_home),
                            ticker_show_register = VALUES(ticker_show_register),
                            ticker_show_dashboard = VALUES(ticker_show_dashboard),
                            ticker_speed = VALUES(ticker_speed),
                            ticker_custom_items = VALUES(ticker_custom_items)")
               ->execute([$instant, $totalDays, $rewardAmt, $isActive, $badgeText, $badgeColor, $title, $description, $perk1Title, $perk1Subtitle, $perk2Title, $perk2Subtitle, $perk3Title, $perk3Subtitle, $tickerEnabled, $tickerShowHome, $tickerShowRegister, $tickerShowDashboard, $tickerSpeed, $tickerCustomItems]);
            $message = "Tester Master Settings & Hero Card Content updated successfully.";

        } elseif ($action === 'save_tester_day_config') {
            $dayNumber = intval($_POST['day_number'] ?? 0);
            $reqLevels = intval($_POST['required_levels'] ?? 10);
            $title = trim($_POST['title'] ?? '');
            $instructions = trim($_POST['instructions'] ?? '');
            $status = intval($_POST['status'] ?? 1);

            if ($dayNumber <= 0 || $reqLevels <= 0) {
                throw new Exception("Day number and required levels must be positive.");
            }

            $db->prepare("INSERT INTO tester_day_configs (day_number, required_levels, title, instructions, status) VALUES (?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE required_levels = VALUES(required_levels), title = VALUES(title), instructions = VALUES(instructions), status = VALUES(status)")
               ->execute([$dayNumber, $reqLevels, $title, $instructions, $status]);
            $message = "Saved Day $dayNumber configuration ($reqLevels target levels).";

        } elseif ($action === 'bulk_tester_action') {
            $bulkAction = $_POST['bulk_action'] ?? '';
            $selectedIds = $_POST['selected_tester_ids'] ?? [];

            if (empty($selectedIds) || !is_array($selectedIds)) {
                throw new Exception("Please select at least one tester user.");
            }

            if ($bulkAction === 'delete') {
                $inPlaceholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $uStmt = $db->prepare("SELECT device_id FROM tester_users WHERE id IN ($inPlaceholders)");
                $uStmt->execute($selectedIds);
                $devIds = array_filter($uStmt->fetchAll(PDO::FETCH_COLUMN));

                if (!empty($devIds)) {
                    $devPlaceholders = implode(',', array_fill(0, count($devIds), '?'));
                    $db->prepare("DELETE FROM tester_daily_progress WHERE device_id IN ($devPlaceholders)")->execute($devIds);
                    $db->prepare("DELETE FROM tester_reward_claims WHERE device_id IN ($devPlaceholders)")->execute($devIds);
                }
                $db->prepare("DELETE FROM tester_users WHERE id IN ($inPlaceholders)")->execute($selectedIds);
                $message = "Permanently deleted " . count($selectedIds) . " tester user(s) and their associated records.";
            } else {
                $statusToSet = ($bulkAction === 'approve') ? 'active' : 'rejected';
                $approvedAt = ($bulkAction === 'approve') ? date('Y-m-d H:i:s') : null;

                $inPlaceholders = implode(',', array_fill(0, count($selectedIds), '?'));
                $params = array_merge([$statusToSet, $approvedAt], $selectedIds);

                $stmt = $db->prepare("UPDATE tester_users SET status = ?, approved_at = COALESCE(approved_at, ?) WHERE id IN ($inPlaceholders)");
                $stmt->execute($params);

                if ($bulkAction === 'approve') {
                    $dayCfgStmt = $db->query("SELECT required_levels FROM tester_day_configs WHERE day_number = 1 LIMIT 1");
                    $dayCfg = $dayCfgStmt ? $dayCfgStmt->fetch(PDO::FETCH_ASSOC) : null;
                    $target1 = $dayCfg ? intval($dayCfg['required_levels']) : 10;

                    $usersStmt = $db->prepare("SELECT id, device_id, current_cycle FROM tester_users WHERE id IN ($inPlaceholders) AND status = 'active'");
                    $usersStmt->execute($selectedIds);
                    while ($u = $usersStmt->fetch(PDO::FETCH_ASSOC)) {
                        $db->prepare("INSERT IGNORE INTO tester_daily_progress (tester_user_id, device_id, cycle, day_number, target_levels, levels_completed, status) VALUES (?, ?, ?, 1, ?, 0, 'in_progress')")
                           ->execute([$u['id'], $u['device_id'], $u['current_cycle'], $target1]);
                    }
                }

                $message = "Bulk updated " . count($selectedIds) . " tester applicant(s) to <strong>" . strtoupper($statusToSet) . "</strong>.";
            }

        } elseif ($action === 'single_tester_action') {
            $testerId = intval($_POST['tester_id'] ?? 0);
            $singleStatus = trim($_POST['status'] ?? '');
            if ($testerId > 0 && in_array($singleStatus, ['active', 'rejected', 'pending', 'completed'])) {
                $approvedAt = ($singleStatus === 'active') ? date('Y-m-d H:i:s') : null;
                $db->prepare("UPDATE tester_users SET status = ?, approved_at = COALESCE(approved_at, ?) WHERE id = ?")
                   ->execute([$singleStatus, $approvedAt, $testerId]);

                if ($singleStatus === 'active') {
                    $uStmt = $db->prepare("SELECT device_id, current_cycle FROM tester_users WHERE id = ? LIMIT 1");
                    $uStmt->execute([$testerId]);
                    $u = $uStmt->fetch(PDO::FETCH_ASSOC);
                    if ($u) {
                        $dayCfgStmt = $db->query("SELECT required_levels FROM tester_day_configs WHERE day_number = 1 LIMIT 1");
                        $dayCfg = $dayCfgStmt ? $dayCfgStmt->fetch(PDO::FETCH_ASSOC) : null;
                        $target1 = $dayCfg ? intval($dayCfg['required_levels']) : 10;
                        $db->prepare("INSERT IGNORE INTO tester_daily_progress (tester_user_id, device_id, cycle, day_number, target_levels, levels_completed, status) VALUES (?, ?, ?, 1, ?, 0, 'in_progress')")
                           ->execute([$testerId, $u['device_id'], $u['current_cycle'], $target1]);
                    }
                }
                $message = "Tester ID #$testerId status updated to <strong>" . strtoupper($singleStatus) . "</strong>.";
            }

        } elseif ($action === 'delete_tester_user') {
            $testerId = intval($_POST['tester_id'] ?? 0);
            if ($testerId > 0) {
                $uStmt = $db->prepare("SELECT device_id FROM tester_users WHERE id = ? LIMIT 1");
                $uStmt->execute([$testerId]);
                $uRow = $uStmt->fetch(PDO::FETCH_ASSOC);
                $devId = $uRow ? $uRow['device_id'] : '';

                if (!empty($devId)) {
                    $db->prepare("DELETE FROM tester_daily_progress WHERE device_id = ?")->execute([$devId]);
                    $db->prepare("DELETE FROM tester_reward_claims WHERE device_id = ?")->execute([$devId]);
                }
                $db->prepare("DELETE FROM tester_users WHERE id = ?")->execute([$testerId]);
                $message = "Tester user #$testerId permanently deleted successfully.";
            }

        } elseif ($action === 'admin_complete_tester_today') {
            $testerId = intval($_POST['tester_id'] ?? 0);
            if ($testerId > 0) {
                $uStmt = $db->prepare("SELECT id, device_id, current_day, current_cycle, status FROM tester_users WHERE id = ? LIMIT 1");
                $uStmt->execute([$testerId]);
                $u = $uStmt->fetch(PDO::FETCH_ASSOC);
                if ($u) {
                    $curDay = intval($u['current_day']);
                    $curCycle = intval($u['current_cycle']);
                    $devId = $u['device_id'];

                    $dayCfgStmt = $db->prepare("SELECT required_levels FROM tester_day_configs WHERE day_number = ? LIMIT 1");
                    $dayCfgStmt->execute([$curDay]);
                    $cfg = $dayCfgStmt->fetch(PDO::FETCH_ASSOC);
                    $targetLevels = $cfg ? intval($cfg['required_levels']) : 10;

                    $progStmt = $db->prepare("SELECT id FROM tester_daily_progress WHERE device_id = ? AND cycle = ? AND day_number = ? LIMIT 1");
                    $progStmt->execute([$devId, $curCycle, $curDay]);
                    $prog = $progStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$prog) {
                        $db->prepare("INSERT INTO tester_daily_progress (tester_user_id, device_id, cycle, day_number, target_levels, levels_completed, status) VALUES (?, ?, ?, ?, ?, ?, 'in_progress')")
                           ->execute([$u['id'], $devId, $curCycle, $curDay, $targetLevels, $targetLevels]);
                    } else {
                        $db->prepare("UPDATE tester_daily_progress SET target_levels = ?, levels_completed = ? WHERE id = ?")
                           ->execute([$targetLevels, $targetLevels, $prog['id']]);
                    }

                    if ($u['status'] !== 'active') {
                        $db->prepare("UPDATE tester_users SET status = 'active', approved_at = COALESCE(approved_at, NOW()) WHERE id = ?")->execute([$testerId]);
                    }

                    $message = "<strong>Success!</strong> Instantly marked Day $curDay ($targetLevels/$targetLevels levels) as complete for Tester #$testerId ({$devId}). User can now submit Day $curDay review in app!";
                }
            }

        } elseif ($action === 'admin_complete_all_tester_days') {
            $testerId = intval($_POST['tester_id'] ?? 0);
            if ($testerId > 0) {
                $uStmt = $db->prepare("SELECT id, device_id, current_cycle FROM tester_users WHERE id = ? LIMIT 1");
                $uStmt->execute([$testerId]);
                $u = $uStmt->fetch(PDO::FETCH_ASSOC);
                if ($u) {
                    $devId = $u['device_id'];
                    $curCycle = intval($u['current_cycle']);

                    $setStmt = $db->query("SELECT total_days FROM tester_settings WHERE id = 1 LIMIT 1");
                    $totalDays = ($setRow = $setStmt->fetch()) ? intval($setRow['total_days']) : 7;

                    $cfgStmt = $db->query("SELECT day_number, required_levels FROM tester_day_configs WHERE status = 1 ORDER BY day_number ASC");
                    $allConfigs = $cfgStmt ? $cfgStmt->fetchAll(PDO::FETCH_ASSOC) : [];
                    $configMap = [];
                    foreach ($allConfigs as $c) { $configMap[intval($c['day_number'])] = intval($c['required_levels']); }

                    for ($d = 1; $d <= $totalDays; $d++) {
                        $targetL = $configMap[$d] ?? 10;
                        $revNote = "Admin Fast-Forward Simulation for Day $d";
                        $db->prepare("INSERT INTO tester_daily_progress (tester_user_id, device_id, cycle, day_number, target_levels, levels_completed, feedback_text, status, completed_at) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
                                      ON DUPLICATE KEY UPDATE target_levels = VALUES(target_levels), levels_completed = VALUES(levels_completed), feedback_text = VALUES(feedback_text), status = 'completed', completed_at = NOW()")
                           ->execute([$u['id'], $devId, $curCycle, $d, $targetL, $targetL, $revNote]);
                    }

                    $db->prepare("UPDATE tester_users SET current_day = ?, status = 'completed', completed_at = NOW() WHERE id = ?")
                       ->execute([$totalDays, $testerId]);

                    $message = "<strong>Success!</strong> Cleared all $totalDays days for Tester #$testerId ({$devId}). Tester status set to COMPLETED. Payout claim is now active in app!";
                }
            }

        } elseif ($action === 'admin_unlock_tester_next_day') {
            $testerId = intval($_POST['tester_id'] ?? 0);
            if ($testerId > 0) {
                $uStmt = $db->prepare("SELECT id, device_id, current_cycle FROM tester_users WHERE id = ? LIMIT 1");
                $uStmt->execute([$testerId]);
                $u = $uStmt->fetch(PDO::FETCH_ASSOC);
                if ($u) {
                    $devId = $u['device_id'];
                    $curCycle = intval($u['current_cycle']);
                    // Shift completed_at timestamps to yesterday so next day becomes playable immediately
                    $db->prepare("UPDATE tester_daily_progress SET completed_at = DATE_SUB(NOW(), INTERVAL 1 DAY) WHERE device_id = ? AND cycle = ? AND status = 'completed'")
                       ->execute([$devId, $curCycle]);
                    $message = "<strong>Success!</strong> Bypassed 24h wait: Next day unlocked immediately for Tester #$testerId ({$devId}).";
                }
            }

        } elseif ($action === 'change_admin_password') {
            $currentPwd = $_POST['current_password'] ?? '';
            $newPwd = $_POST['new_password'] ?? '';
            $confirmPwd = $_POST['confirm_password'] ?? '';
            $adminUserId = intval($_SESSION['admin_user_id'] ?? 1);

            if (empty($currentPwd) || empty($newPwd)) {
                throw new Exception("Both current and new passwords are required.");
            }
            if ($newPwd !== $confirmPwd) {
                throw new Exception("New password and confirmation password do not match.");
            }
            if (strlen($newPwd) < 6) {
                throw new Exception("New password must be at least 6 characters long.");
            }

            $userStmt = $db->prepare("SELECT password_hash FROM admin_users WHERE id = ? LIMIT 1");
            $userStmt->execute([$adminUserId]);
            $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$userRow || !password_verify($currentPwd, $userRow['password_hash'])) {
                throw new Exception("Current password is incorrect.");
            }

            $newHash = password_hash($newPwd, PASSWORD_BCRYPT);
            $db->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?")->execute([$newHash, $adminUserId]);
            $message = "<strong>Success!</strong> Admin password has been updated successfully.";

        } elseif ($action === 'update_tester_claim_status') {
            $claimId = intval($_POST['claim_id'] ?? 0);
            $claimStatus = trim($_POST['claim_status'] ?? '');
            $voucherCode = trim($_POST['voucher_code'] ?? '');
            $adminNotes = trim($_POST['admin_notes'] ?? '');

            if ($claimId > 0 && in_array($claimStatus, ['sent', 'rejected', 'pending'])) {
                $db->prepare("UPDATE tester_reward_claims SET status = ?, voucher_code = COALESCE(NULLIF(?, ''), voucher_code), admin_notes = COALESCE(NULLIF(?, ''), admin_notes) WHERE id = ?")
                   ->execute([$claimStatus, $voucherCode, $adminNotes, $claimId]);
                $message = "Tester Claim #$claimId updated to <strong>" . strtoupper($claimStatus) . "</strong>" . (!empty($voucherCode) ? " with Voucher: <code>" . htmlspecialchars($voucherCode) . "</code>" : "") . ".";
            }

        } elseif ($action === 'bulk_assign_vouchers') {
            $claimIds = $_POST['bulk_voucher_claim_ids'] ?? [];
            if (!is_array($claimIds)) {
                $claimIds = explode(',', (string)$claimIds);
            }
            $claimIds = array_filter(array_map('intval', $claimIds));
            $rawCodes = trim($_POST['bulk_voucher_codes'] ?? '');

            if (empty($claimIds)) {
                throw new Exception("No claims selected for bulk voucher assignment.");
            }

            $codeLines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $rawCodes)));
            $codeLines = array_values($codeLines);

            if (count($codeLines) < count($claimIds)) {
                throw new Exception("You selected " . count($claimIds) . " claim(s), but provided only " . count($codeLines) . " voucher code(s). Please provide at least " . count($claimIds) . " codes (1 per line).");
            }

            $upStmt = $db->prepare("UPDATE tester_reward_claims SET voucher_code = ?, status = 'sent' WHERE id = ?");
            $assignedCount = 0;
            foreach ($claimIds as $idx => $cid) {
                $assignedCode = $codeLines[$idx];
                $upStmt->execute([$assignedCode, $cid]);
                $assignedCount++;
            }
            $message = "<strong>Success!</strong> Successfully assigned <strong>$assignedCount</strong> unique voucher codes to selected claims and marked as <strong>SENT</strong>.";

        } elseif ($action === 'delete_single_tester_claim') {
            $claimId = intval($_POST['claim_id'] ?? 0);
            if ($claimId > 0) {
                $db->prepare("DELETE FROM tester_reward_claims WHERE id = ?")->execute([$claimId]);
                $message = "Tester Claim #$claimId permanently deleted.";
            }

        } elseif ($action === 'bulk_tester_claim_action') {
            $bulkAction = trim($_POST['bulk_action'] ?? '');
            $claimIds = $_POST['claim_ids'] ?? [];
            if (!is_array($claimIds)) {
                $claimIds = explode(',', (string)$claimIds);
            }
            $claimIds = array_filter(array_map('intval', $claimIds));

            if (empty($claimIds)) {
                throw new Exception("No claims selected for bulk action.");
            }

            $placeholders = implode(',', array_fill(0, count($claimIds), '?'));
            if (in_array($bulkAction, ['sent', 'rejected', 'pending'])) {
                $params = array_merge([$bulkAction], $claimIds);
                $db->prepare("UPDATE tester_reward_claims SET status = ? WHERE id IN ($placeholders)")->execute($params);
                $message = "Successfully updated <strong>" . count($claimIds) . "</strong> claims to <strong>" . strtoupper($bulkAction) . "</strong>.";
            } elseif ($bulkAction === 'delete') {
                $db->prepare("DELETE FROM tester_reward_claims WHERE id IN ($placeholders)")->execute($claimIds);
                $message = "Successfully deleted <strong>" . count($claimIds) . "</strong> tester claims.";
            }

        } elseif ($action === 'export_tester_claims_csv') {
            $tcStmt = $db->query("
                SELECT tc.id, tu.name, tu.email, tu.mobile, tc.device_id, tc.cycle, tc.amount, tc.payment_method, tc.account_details, tc.voucher_code, tc.status, tc.created_at
                FROM tester_reward_claims tc
                LEFT JOIN tester_users tu ON tc.device_id = tu.device_id
                ORDER BY tc.id DESC
            ");
            $exportData = $tcStmt ? $tcStmt->fetchAll(PDO::FETCH_ASSOC) : [];

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=tester_reward_claims_' . date('Y-m-d_His') . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Claim ID', 'Tester Name', 'Email', 'Mobile', 'Device ID', 'Round (Cycle)', 'Amount (INR)', 'Payment Method', 'Account Details', 'Voucher Code', 'Status', 'Submitted Date']);
            foreach ($exportData as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['name'] ?: 'Tester',
                    $row['email'] ?: 'N/A',
                    $row['mobile'] ?: 'N/A',
                    $row['device_id'],
                    'Round ' . $row['cycle'] . ($row['cycle'] == 1 ? ' (1st Reward)' : ' (Repeat)'),
                    $row['amount'],
                    $row['payment_method'],
                    $row['account_details'],
                    $row['voucher_code'] ?: 'N/A',
                    strtoupper($row['status']),
                    $row['created_at']
                ]);
            }
            fclose($output);
            exit;

        } elseif ($action === 'save_tester_payout_method') {
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $placeholder = trim($_POST['input_placeholder'] ?? '');
            $status = intval($_POST['status'] ?? 1);
            $sortOrder = intval($_POST['sort_order'] ?? 0);

            if (empty($name)) {
                throw new Exception("Payment Method Name is required.");
            }

            if ($id > 0) {
                $stmt = $db->prepare("UPDATE tester_payout_methods SET name = ?, input_placeholder = ?, status = ?, sort_order = ? WHERE id = ?");
                $stmt->execute([$name, $placeholder, $status, $sortOrder, $id]);
                $message = "Payout Method <strong>" . htmlspecialchars($name) . "</strong> updated successfully.";
            } else {
                $stmt = $db->prepare("INSERT INTO tester_payout_methods (name, input_placeholder, status, sort_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $placeholder, $status, $sortOrder]);
                $message = "New Payout Method <strong>" . htmlspecialchars($name) . "</strong> created successfully.";
            }

        } elseif ($action === 'toggle_tester_payout_method') {
            $id = intval($_POST['id'] ?? 0);
            $status = intval($_POST['status'] ?? 0);
            if ($id > 0) {
                $db->prepare("UPDATE tester_payout_methods SET status = ? WHERE id = ?")->execute([$status, $id]);
                $message = "Payout Method ID #$id status set to " . ($status === 1 ? 'ACTIVE' : 'DISABLED') . ".";
            }

        } elseif ($action === 'delete_tester_payout_method') {
            $id = intval($_POST['id'] ?? 0);
            if ($id > 0) {
                $db->prepare("DELETE FROM tester_payout_methods WHERE id = ?")->execute([$id]);
                $message = "Payout Method ID #$id deleted successfully.";
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }

    if (!empty($message)) {
        $_SESSION['admin_message'] = $message;
    }
    if (!empty($error)) {
        $_SESSION['admin_error'] = $error;
    }

    $redirectTab = trim($_POST['redirect_tab'] ?? '');
    $redirectUrl = 'admin.php' . (!empty($redirectTab) ? '#' . urlencode($redirectTab) : '');
    header("Location: " . $redirectUrl);
    exit;
}

// Retrieve flash message from session
$message = $_SESSION['admin_message'] ?? '';
$error = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_message'], $_SESSION['admin_error']);

// 3. Fetch Dashboard Data & Counts
$appSettings = [];
try {
    $adStmt = $db->query("SELECT * FROM app_settings LIMIT 1");
    $appSettings = $adStmt ? $adStmt->fetch(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

$rewardsEnabled = isset($appSettings['rewards_enabled']) ? intval($appSettings['rewards_enabled']) : 1;

$allBanners = [];
try {
    $bStmt = $db->query("SELECT * FROM app_banners ORDER BY sort_order ASC, id ASC");
    $allBanners = $bStmt ? $bStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

$testerSettings = ['instant_approval' => 1, 'reward_amount' => 150, 'total_days' => 7, 'is_active' => 1];
try {
    $tsStmt = $db->query("SELECT * FROM tester_settings WHERE id = 1 LIMIT 1");
    if ($tsStmt && ($tsRow = $tsStmt->fetch(PDO::FETCH_ASSOC))) {
        $testerSettings = $tsRow;
    }
} catch (Exception $e) {}

$testerDayConfigs = [];
try {
    $tdStmt = $db->query("SELECT * FROM tester_day_configs ORDER BY day_number ASC");
    $testerDayConfigs = $tdStmt ? $tdStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

$testerUsers = [];
try {
    $tuStmt = $db->query("SELECT * FROM tester_users ORDER BY id DESC LIMIT 100");
    $testerUsers = $tuStmt ? $tuStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    if (!empty($testerUsers)) {
        $devIds = array_filter(array_column($testerUsers, 'device_id'));
        if (!empty($devIds)) {
            $inDevs = implode(',', array_fill(0, count($devIds), '?'));
            $progStmt = $db->prepare("
                SELECT device_id, cycle,
                       COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
                       MAX(CASE WHEN feedback_text IS NOT NULL AND feedback_text != '' THEN feedback_text END) as latest_feedback
                FROM tester_daily_progress
                WHERE device_id IN ($inDevs)
                GROUP BY device_id, cycle
            ");
            $progStmt->execute(array_values($devIds));
            $progMap = [];
            while ($p = $progStmt->fetch(PDO::FETCH_ASSOC)) {
                $progMap[$p['device_id'] . '_' . $p['cycle']] = $p;
            }
            foreach ($testerUsers as &$tu) {
                $k = $tu['device_id'] . '_' . $tu['current_cycle'];
                $tu['completed_days_count'] = isset($progMap[$k]) ? intval($progMap[$k]['completed_count']) : 0;
                $tu['latest_feedback'] = isset($progMap[$k]) ? $progMap[$k]['latest_feedback'] : null;
            }
            unset($tu);
        }
    }
} catch (Exception $e) {}

$allTesterReviews = [];
try {
    $displayedDevIds = array_filter(array_column($testerUsers, 'device_id'));
    if (!empty($displayedDevIds)) {
        $inDevs = implode(',', array_fill(0, count($displayedDevIds), '?'));
        $revStmt = $db->prepare("
            SELECT device_id, cycle, day_number, target_levels, levels_completed, feedback_text, status, completed_at
            FROM tester_daily_progress
            WHERE device_id IN ($inDevs) AND feedback_text IS NOT NULL AND feedback_text != ''
            ORDER BY cycle ASC, day_number ASC
        ");
        $revStmt->execute(array_values($displayedDevIds));
        while ($r = $revStmt->fetch(PDO::FETCH_ASSOC)) {
            $devId = $r['device_id'];
            $cyc = intval($r['cycle']);
            if (!isset($allTesterReviews[$devId])) $allTesterReviews[$devId] = [];
            if (!isset($allTesterReviews[$devId][$cyc])) $allTesterReviews[$devId][$cyc] = [];
            $allTesterReviews[$devId][$cyc][] = [
                'day' => intval($r['day_number']),
                'target' => intval($r['target_levels']),
                'completed' => intval($r['levels_completed']),
                'feedback' => $r['feedback_text'],
                'completed_at' => $r['completed_at'] ? date('d M Y, h:i A', strtotime($r['completed_at'])) : 'N/A'
            ];
        }
    }
} catch (Exception $e) {}

$testerClaims = [];
$totalClaimAmount = 0;
$pendingClaimAmount = 0;
$pendingClaimCount = 0;
$sentClaimAmount = 0;
$sentClaimCount = 0;
$rejectedClaimCount = 0;
$repeatTesterClaimCount = 0;

try {
    $tcStmt = $db->query("
        SELECT tc.*, tu.name, tu.mobile, tu.email, tu.is_finished 
        FROM tester_reward_claims tc
        LEFT JOIN tester_users tu ON tc.device_id = tu.device_id
        ORDER BY tc.id DESC 
        LIMIT 200
    ");
    $testerClaims = $tcStmt ? $tcStmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($testerClaims as $c) {
        $amt = intval($c['amount']);
        $totalClaimAmount += $amt;
        if ($c['status'] === 'pending') {
            $pendingClaimCount++;
            $pendingClaimAmount += $amt;
        } elseif ($c['status'] === 'sent') {
            $sentClaimCount++;
            $sentClaimAmount += $amt;
        } elseif ($c['status'] === 'rejected') {
            $rejectedClaimCount++;
        }
        if (intval($c['cycle']) > 1) {
            $repeatTesterClaimCount++;
        }
    }
} catch (Exception $e) {}

$jsonPendingTesterClaims = [];
foreach ($testerClaims as $c) {
    if ($c['status'] === 'pending') {
        $jsonPendingTesterClaims[] = [
            'id' => intval($c['id']),
            'name' => $c['name'] ?: 'Tester',
            'email' => $c['email'] ?: '',
            'mobile' => $c['mobile'] ?: '',
            'method' => $c['payment_method'],
            'account' => $c['account_details'],
            'amount' => intval($c['amount']),
            'cycle' => intval($c['cycle'] ?? 1),
            'is_voucher' => isVoucherMethod($c['payment_method'])
        ];
    }
}

$testerPayoutMethods = [];
try {
    $tpmStmt = $db->query("SELECT * FROM tester_payout_methods ORDER BY sort_order ASC, id ASC");
    $testerPayoutMethods = $tpmStmt ? $tpmStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

$allRewardSettings = [];
try {
    $rStmt = $db->query("SELECT * FROM reward_settings ORDER BY required_level ASC");
    $allRewardSettings = $rStmt ? $rStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

$allUserClaims = [];
try {
    $cStmt = $db->query("SELECT * FROM rewards ORDER BY id DESC LIMIT 50");
    $allUserClaims = $cStmt ? $cStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}

$stuckUsers = [];
try {
    $stuckStmt = $db->query("
        SELECT uc.device_id, uc.rewardbro_uid, uc.offer_id, uc.created_at as campaign_created_at, u.current_level
        FROM user_campaigns uc
        JOIN users u ON uc.device_id = u.device_id
        WHERE uc.status = 'active'
          AND u.current_level > 1
        ORDER BY uc.created_at DESC
        LIMIT 10
    ");
    $candUsers = $stuckStmt ? $stuckStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    if (!empty($candUsers)) {
        $candDevIds = array_column($candUsers, 'device_id');
        $inCand = implode(',', array_fill(0, count($candDevIds), '?'));
        $lhStmt = $db->prepare("SELECT device_id, level, created_at FROM level_history WHERE device_id IN ($inCand)");
        $lhStmt->execute($candDevIds);
        $allLh = $lhStmt->fetchAll(PDO::FETCH_ASSOC);
        $lhByDev = [];
        foreach ($allLh as $row) {
            $lhByDev[$row['device_id']][] = $row;
        }
        foreach ($candUsers as $cu) {
            $dId = $cu['device_id'];
            $cTime = $cu['campaign_created_at'];
            $history = $lhByDev[$dId] ?? [];
            $leftoverCount = 0;
            $newCount = 0;
            foreach ($history as $h) {
                if ($h['created_at'] < $cTime) $leftoverCount++;
                if ($h['created_at'] >= $cTime && intval($h['level']) >= 2) $newCount++;
            }
            if ($leftoverCount > 0 && $newCount === 0) {
                $cu['leftover_levels'] = $leftoverCount;
                $stuckUsers[] = $cu;
            }
        }
    }
} catch (Exception $e) {}

// Metric calculations
$countPendingTesters = count(array_filter($testerUsers, fn($u) => $u['status'] === 'pending'));
$countActiveTesters = count(array_filter($testerUsers, fn($u) => $u['status'] === 'active'));
$countPendingTesterClaims = count(array_filter($testerClaims, fn($c) => $c['status'] === 'pending'));
$countPendingRewardClaims = count(array_filter($allUserClaims, fn($c) => $c['status'] === 'pending'));
$countActiveBanners = count(array_filter($allBanners, fn($b) => intval($b['status']) === 1));

// TopOn App Settings & Ad Controls Fetch
$currentAppSettings = $appSettings;

$adControlsStmt = $db->query("SELECT * FROM ad_controls ORDER BY id ASC");
$allAdControls = $adControlsStmt ? $adControlsStmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Convert allAdControls to key-value map for quick lookup
$adControlMap = [];
foreach ($allAdControls as $ac) {
    $adControlMap[$ac['screen_key']] = $ac['ad_type'];
}

// Fetch Push Notifications History
$allPushNotifications = [];
try {
    $pnStmt = $db->query("SELECT * FROM push_notifications ORDER BY id DESC LIMIT 50");
    $allPushNotifications = $pnStmt ? $pnStmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipecraze Game Control Center • Admin Studio</title>
    <!-- Modern Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #090d16;
            --bg-sidebar: #0f172a;
            --bg-card: #131d33;
            --bg-card-hover: #182542;
            --bg-input: #0b1220;
            --border: #1e293b;
            --border-hover: #334155;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --primary-glow: rgba(99, 102, 241, 0.25);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.2);
            --warning: #f59e0b;
            --warning-glow: rgba(245, 158, 11, 0.2);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.2);
            --cyan: #06b6d4;
            --purple: #a855f7;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 18px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', -apple-system, sans-serif;
        }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        code, pre {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Sidebar Styling */
        .sidebar {
            width: 270px;
            background-color: var(--bg-sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 50;
            padding: 24px 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 12px 24px 12px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
        }

        .brand-logo {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 16px var(--primary-glow);
        }

        .brand-text h3 {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.3px;
        }

        .brand-text p {
            font-size: 11px;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .nav-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
        }

        .nav-item:hover {
            color: var(--text-main);
            background-color: rgba(255, 255, 255, 0.04);
        }

        .nav-item.active {
            color: #ffffff;
            background: linear-gradient(90deg, rgba(99, 102, 241, 0.18), rgba(99, 102, 241, 0.05));
            border-left: 3px solid var(--primary);
        }

        .nav-badge {
            margin-left: auto;
            background: var(--primary);
            color: white;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
        }

        .nav-badge.danger { background: var(--danger); }
        .nav-badge.warning { background: var(--warning); color: #000; }

        .sidebar-footer {
            padding-top: 16px;
            border-top: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-dim);
        }

        /* Main Workspace */
        .main-wrapper {
            margin-left: 270px;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        /* Top Header */
        .topbar {
            height: 70px;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .topbar-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .topbar-title h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.4px;
        }

        .security-badge {
            background: rgba(16, 185, 129, 0.12);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--success);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .security-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            background: var(--success);
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 8px var(--success);
        }

        /* Content Area */
        .content {
            padding: 32px;
            max-width: 1400px;
            width: 100%;
        }

        /* Notifications / Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: var(--radius-md);
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            line-height: 1.5;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert.success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        /* Metric Cards Grid */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .metric-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.2s ease;
        }

        .metric-card:hover {
            border-color: var(--border-hover);
            transform: translateY(-2px);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .metric-info h4 {
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }

        .metric-info .metric-num {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
        }

        /* Main Container Cards */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 28px;
            margin-bottom: 28px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-title h2 {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .card-desc {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Form Controls */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 18px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
        }

        input[type="text"],
        input[type="number"],
        input[type="datetime-local"],
        input[type="email"],
        select,
        textarea {
            width: 100%;
            padding: 11px 14px;
            background: var(--bg-input);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-main);
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
        }

        .btn-primary {
            background: var(--primary);
            color: #ffffff;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
            box-shadow: 0 4px 14px var(--primary-glow);
        }

        .btn-success {
            background: var(--success);
            color: #ffffff;
        }
        .btn-success:hover {
            background: #059669;
            box-shadow: 0 4px 14px var(--success-glow);
        }

        .btn-danger {
            background: var(--danger);
            color: #ffffff;
        }
        .btn-danger:hover {
            background: #dc2626;
            box-shadow: 0 4px 14px var(--danger-glow);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: var(--border-hover);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
            border-radius: 6px;
        }

        /* Data Tables */
        .table-responsive {
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            background: var(--bg-input);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: left;
        }

        th {
            background: rgba(15, 23, 42, 0.7);
            padding: 14px 16px;
            font-weight: 700;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
        }

        td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
            vertical-align: middle;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background-color: rgba(255, 255, 255, 0.02);
        }

        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .badge-active { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-pending { background: rgba(245, 158, 11, 0.15); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-rejected { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .badge-completed { background: rgba(99, 102, 241, 0.15); color: #818cf8; border: 1px solid rgba(99, 102, 241, 0.3); }

        /* Search & Filter Bar */
        .filter-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 380px;
        }

        .search-box input {
            padding-left: 36px;
        }

        .search-box::before {
            content: '🔍';
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 14px;
            opacity: 0.6;
        }

        /* Custom Toggle Switch */
        .toggle-switch-card {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(16, 185, 129, 0.05));
            border: 1px solid rgba(99, 102, 241, 0.3);
            border-radius: var(--radius-md);
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .toggle-switch-card h3 {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 4px;
        }

        .toggle-switch-card p {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* Tab Panels */
        .tab-panel {
            display: none;
            animation: fadeIn 0.25s ease;
        }

        .tab-panel.active {
            display: block;
        }

        /* Subtabs */
        .subtab-nav {
            display: flex;
            gap: 8px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 24px;
            padding-bottom: 12px;
        }

        .subtab-btn {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .subtab-btn:hover {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.04);
        }

        .subtab-btn.active {
            color: #ffffff;
            background: var(--primary);
        }

        /* Banner Thumbnail */
        .banner-thumb {
            width: 100px;
            height: 50px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg-input);
        }

        /* Modal Dialog */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 20px;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 600px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            overflow: hidden;
            animation: modalPop 0.25s ease;
        }

        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-body {
            padding: 24px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: rgba(15, 23, 42, 0.4);
        }

        @media (max-width: 900px) {
            .sidebar { width: 70px; padding: 16px 8px; }
            .brand-text, .nav-text, .sidebar-footer { display: none; }
            .main-wrapper { margin-left: 70px; }
            .topbar { padding: 0 16px; }
            .content { padding: 16px; }
        }
    </style>
</head>
<body>

<!-- Left Navigation Sidebar -->
<aside class="sidebar">
    <div class="brand">
        <div class="brand-logo">🎯</div>
        <div class="brand-text">
            <h3>Pipecraze Control</h3>
            <p>Admin Studio v2.0</p>
        </div>
    </div>

    <ul class="nav-list">
        <li class="nav-item active" onclick="switchTab('dashboard')">
            <span>📊</span>
            <span class="nav-text">Dashboard</span>
        </li>
        <li class="nav-item" onclick="switchTab('testers')">
            <span>👥</span>
            <span class="nav-text">Tester Program</span>
            <?php if ($countPendingTesters > 0): ?>
                <span class="nav-badge warning"><?= $countPendingTesters ?></span>
            <?php endif; ?>
        </li>
        <li class="nav-item" onclick="switchTab('banners')">
            <span>🖼️</span>
            <span class="nav-text">Banners Slider</span>
            <span class="nav-badge"><?= count($allBanners) ?></span>
        </li>
        <li class="nav-item" onclick="switchTab('rewards')">
            <span>🎁</span>
            <span class="nav-text">Rewards System</span>
            <?php if ($countPendingRewardClaims > 0): ?>
                <span class="nav-badge danger"><?= $countPendingRewardClaims ?></span>
            <?php endif; ?>
        </li>
        <li class="nav-item" onclick="switchTab('tools')">
            <span>⚡</span>
            <span class="nav-text">Campaign & Tools</span>
            <?php if (count($stuckUsers) > 0): ?>
                <span class="nav-badge danger"><?= count($stuckUsers) ?> Stuck</span>
            <?php endif; ?>
        </li>
        <li class="nav-item" onclick="switchTab('ads')">
            <span>📺</span>
            <span class="nav-text">Ads & TopOn</span>
        </li>
        <li class="nav-item" onclick="switchTab('notifications')">
            <span>🔔</span>
            <span class="nav-text">Push Notifications</span>
            <span class="nav-badge" style="background: rgba(99, 102, 241, 0.2); color: #818cf8;"><?= count($allPushNotifications) ?></span>
        </li>
    </ul>

    <div class="sidebar-footer">
        <span>Admin: <strong style="color: #fff;"><?= htmlspecialchars($_SESSION['admin_username'] ?? 'admin') ?></strong></span>
        <a href="?action=logout" style="color: #f87171; text-decoration: none; font-weight: 600;" onclick="return confirm('Are you sure you want to log out?');">Logout 🚪</a>
    </div>
</aside>

<!-- Main Workspace -->
<main class="main-wrapper">
    <!-- Topbar -->
    <header class="topbar">
        <div class="topbar-title">
            <h1 id="pageHeading">Admin Dashboard Overview</h1>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="security-badge">🛡️ Authenticated (<?= htmlspecialchars($_SESSION['admin_username'] ?? 'admin') ?>)</div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="openChangePasswordModal()">🔑 Change Password</button>
            <a href="admin.php" class="btn btn-secondary btn-sm" title="Refresh Dashboard">🔄 Refresh</a>
            <a href="?action=logout" class="btn btn-danger btn-sm" title="Log Out" onclick="return confirm('Are you sure you want to log out?');">🚪 Logout</a>
        </div>
    </header>

    <div class="content">
        <!-- Action Notification Message -->
        <?php if (!empty($message)): ?>
            <div class="alert success">
                <span style="font-size: 18px;">✅</span>
                <div><?= $message ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert error">
                <span style="font-size: 18px;">⚠️</span>
                <div><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- ========================================================== -->
        <!-- TAB 1: DASHBOARD OVERVIEW -->
        <!-- ========================================================== -->
        <div id="tab-dashboard" class="tab-panel active">
            <!-- Metrics Row -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(99, 102, 241, 0.15); color: var(--primary);">👥</div>
                    <div class="metric-info">
                        <h4>Total Testers</h4>
                        <div class="metric-num"><?= count($testerUsers) ?></div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(245, 158, 11, 0.15); color: var(--warning);">⏳</div>
                    <div class="metric-info">
                        <h4>Pending Applicants</h4>
                        <div class="metric-num" style="color: var(--warning);"><?= $countPendingTesters ?></div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(16, 185, 129, 0.15); color: var(--success);">🖼️</div>
                    <div class="metric-info">
                        <h4>Active Banners</h4>
                        <div class="metric-num" style="color: var(--success);"><?= $countActiveBanners ?></div>
                    </div>
                </div>

                <div class="metric-card">
                    <div class="metric-icon" style="background: rgba(239, 68, 68, 0.15); color: var(--danger);">💰</div>
                    <div class="metric-info">
                        <h4>Pending Payouts</h4>
                        <div class="metric-num" style="color: var(--danger);"><?= $countPendingTesterClaims + $countPendingRewardClaims ?></div>
                    </div>
                </div>
            </div>

            <!-- Global Rewards Master Card -->
            <div class="toggle-switch-card">
                <div>
                    <h3>🎮 Global Rewards Master Switch (In-App)</h3>
                    <p>Controls whether the Rewards button & Center are visible on the Home Screen & Level Select.</p>
                </div>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                    <input type="hidden" name="action" value="toggle_rewards_master">
                    <input type="hidden" name="status" value="<?= $rewardsEnabled === 1 ? '0' : '1' ?>">
                    <button type="submit" class="btn <?= $rewardsEnabled === 1 ? 'btn-success' : 'btn-danger' ?>" style="font-size: 14px; padding: 10px 20px;">
                        <?= $rewardsEnabled === 1 ? '🟢 REWARDS ENABLED (ON)' : '🔴 REWARDS HIDDEN (OFF)' ?>
                    </button>
                </form>
            </div>

            <!-- Stuck Users Alert Card (If any) -->
            <?php if (!empty($stuckUsers)): ?>
                <div class="card" style="border: 2px solid var(--danger); background: rgba(239, 68, 68, 0.05);">
                    <div class="card-header">
                        <div class="card-title">
                            <span style="font-size: 22px;">🚨</span>
                            <div>
                                <h2 style="color: var(--danger);">Action Required: <?= count($stuckUsers) ?> Stuck User(s) Detected</h2>
                                <p class="card-desc">Active campaigns with past leftover level history preventing fresh postback triggers.</p>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Device ID</th>
                                    <th>UID</th>
                                    <th>Offer</th>
                                    <th>Current Lvl</th>
                                    <th>Campaign Start</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stuckUsers as $stuck): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($stuck['device_id']) ?></code></td>
                                        <td><code><?= htmlspecialchars($stuck['rewardbro_uid']) ?></code></td>
                                        <td><?= htmlspecialchars($stuck['offer_id']) ?></td>
                                        <td><span class="badge badge-rejected">Lvl <?= $stuck['current_level'] ?></span></td>
                                        <td style="color: var(--text-dim);"><?= htmlspecialchars($stuck['campaign_created_at']) ?></td>
                                        <td>
                                            <form method="POST" style="margin:0;">
                                                <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                                                <input type="hidden" name="action" value="fix_stuck_user">
                                                <input type="hidden" name="device_id" value="<?= htmlspecialchars($stuck['device_id']) ?>">
                                                <button type="submit" class="btn btn-success btn-sm">1-Click Fix (Reset Lvl 1)</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Action Hub -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <h2>⚡ Quick Control Hub</h2>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
                    <div style="background: var(--bg-input); border: 1px solid var(--border); border-radius: 12px; padding: 20px;">
                        <h4 style="font-size: 15px; margin-bottom: 6px;">👥 Tester Management</h4>
                        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px;">Instant Approval is <strong><?= intval($testerSettings['instant_approval']) === 1 ? 'ENABLED' : 'MANUAL' ?></strong>. Review applicants or payout claims.</p>
                        <button onclick="switchTab('testers')" class="btn btn-primary btn-sm">Open Tester Studio →</button>
                    </div>

                    <div style="background: var(--bg-input); border: 1px solid var(--border); border-radius: 12px; padding: 20px;">
                        <h4 style="font-size: 15px; margin-bottom: 6px;">🖼️ Home Slider Banners</h4>
                        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px;"><?= count($allBanners) ?> banner posters configured with auto-routing actions.</p>
                        <button onclick="switchTab('banners')" class="btn btn-primary btn-sm">Manage Banners →</button>
                    </div>

                    <div style="background: var(--bg-input); border: 1px solid var(--border); border-radius: 12px; padding: 20px;">
                        <h4 style="font-size: 15px; margin-bottom: 6px;">🚀 1-Click Auto Postback Solver</h4>
                        <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 14px;">Simulate sequential gameplay and trigger postbacks for any campaign.</p>
                        <button onclick="switchTab('tools')" class="btn btn-primary btn-sm">Launch Tool →</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- TAB 2: 7-DAY TESTER PROGRAM -->
        <!-- ========================================================== -->
        <div id="tab-testers" class="tab-panel">
            <div class="subtab-nav">
                <button class="subtab-btn active" onclick="switchTesterSubtab('applicants')">👥 Applicants & Progress (<?= count($testerUsers) ?>)</button>
                <button class="subtab-btn" onclick="switchTesterSubtab('payouts')">💰 Payout Claims (<?= count($testerClaims) ?>)</button>
                <button class="subtab-btn" onclick="switchTesterSubtab('settings')">⚙️ Program Rules & 7-Day Targets</button>
            </div>

            <!-- Subtab A: Applicants -->
            <div id="tester-subtab-applicants">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <div>
                                <h2>Tester Applicants & Daily Progress</h2>
                                <p class="card-desc">Review registered testers, bulk approve/reject, and inspect daily bug review logs.</p>
                            </div>
                        </div>
                    </div>

                    <form method="POST" id="bulkTesterForm">
                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                        <input type="hidden" name="action" value="bulk_tester_action">
                        <input type="hidden" name="bulk_action" id="bulkActionInput" value="approve">
                        <input type="hidden" name="redirect_tab" value="testers">

                        <!-- Filter & Bulk Actions Bar -->
                        <div class="filter-bar">
                            <div class="search-box">
                                <input type="text" id="testerSearchInput" placeholder="Filter by name, mobile, email, or device...">
                            </div>

                            <div style="display: flex; gap: 10px; align-items: center;">
                                <span style="font-size: 13px; color: var(--text-muted);">Bulk Actions:</span>
                                <button type="button" class="btn btn-success btn-sm" onclick="submitBulk('approve')">✓ Bulk Approve</button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="submitBulk('reject')">✕ Bulk Reject</button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="submitBulk('delete')">🗑️ Bulk Delete</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table id="testerTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;">
                                            <input type="checkbox" id="selectAllTesters" style="cursor: pointer;">
                                        </th>
                                        <th>Applicant</th>
                                        <th>Contact Details</th>
                                        <th>Device ID</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th>Reviews</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($testerUsers)): ?>
                                        <tr>
                                            <td colspan="8" style="text-align: center; color: var(--text-dim); padding: 30px;">
                                                No tester applicants registered yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($testerUsers as $tUser): ?>
                                            <tr class="tester-row" data-search="<?= strtolower(htmlspecialchars($tUser['name'] . ' ' . $tUser['mobile'] . ' ' . $tUser['email'] . ' ' . $tUser['device_id'] . ' ' . $tUser['status'])) ?>">
                                                <td style="text-align: center;">
                                                    <input type="checkbox" name="selected_tester_ids[]" value="<?= $tUser['id'] ?>" class="tester-checkbox" style="cursor: pointer;">
                                                </td>
                                                <td>
                                                    <strong style="color: #ffffff;"><?= htmlspecialchars($tUser['name']) ?></strong>
                                                    <div style="font-size: 11px; color: var(--text-dim);">Reg: <?= date('d M Y', strtotime($tUser['registered_at'])) ?></div>
                                                </td>
                                                <td>
                                                    <div>📱 <?= htmlspecialchars($tUser['mobile']) ?></div>
                                                    <div style="font-size: 12px; color: var(--text-muted);">✉️ <?= htmlspecialchars($tUser['email']) ?></div>
                                                </td>
                                                <td>
                                                    <code style="font-size: 12px;"><?= htmlspecialchars(substr($tUser['device_id'], 0, 14)) ?>...</code>
                                                    <div style="margin-top: 4px;">
                                                        <button type="button" class="btn btn-primary btn-sm" style="font-size: 10.5px; padding: 2px 7px; background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.4); color: #c7d2fe; display: flex; align-items: center; gap: 3px;" onclick="openUserAuditModal('<?= htmlspecialchars(addslashes($tUser['device_id'])) ?>', '<?= htmlspecialchars(addslashes($tUser['name'])) ?>')">
                                                            🔍 Audit Profile
                                                        </button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="font-weight: 700; color: var(--primary);">Day <?= $tUser['current_day'] ?> / <?= $testerSettings['total_days'] ?></span>
                                                    <div style="font-size: 11px; color: var(--text-dim);">Cycle #<?= $tUser['current_cycle'] ?> (<?= $tUser['completed_days_count'] ?> days cleared)</div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $st = $tUser['status'];
                                                    $badgeClass = ($st === 'active') ? 'badge-active' : (($st === 'pending') ? 'badge-pending' : (($st === 'completed') ? 'badge-completed' : 'badge-rejected'));
                                                    ?>
                                                    <span class="badge <?= $badgeClass ?>"><?= strtoupper($st) ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $uRevList = $allTesterReviews[$tUser['device_id']][intval($tUser['current_cycle'])] ?? [];
                                                    $uRevCount = count($uRevList);
                                                    ?>
                                                    <?php if ($uRevCount > 0): ?>
                                                        <button type="button" class="btn btn-secondary btn-sm" style="border-color: rgba(99, 102, 241, 0.4); color: #a5b4fc; background: rgba(99, 102, 241, 0.1);" onclick="showAllTesterReviews('<?= htmlspecialchars(addslashes($tUser['name'])) ?> (Cycle #<?= $tUser['current_cycle'] ?>)', <?= htmlspecialchars(json_encode($uRevList), ENT_QUOTES, 'UTF-8') ?>)">
                                                            📝 <?= $uRevCount ?> Day<?= $uRevCount > 1 ? 's' : '' ?> Review<?= $uRevCount > 1 ? 's' : '' ?>
                                                        </button>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-dim); font-size: 12px;">No reviews yet</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div style="display: flex; flex-direction: column; gap: 6px;">
                                                        <div style="display: flex; gap: 6px;">
                                                            <?php if ($tUser['status'] !== 'active'): ?>
                                                                <button type="button" class="btn btn-success btn-sm" onclick="setSingleTesterStatus(<?= $tUser['id'] ?>, 'active')">Approve</button>
                                                            <?php endif; ?>
                                                            <?php if ($tUser['status'] !== 'rejected'): ?>
                                                                <button type="button" class="btn btn-secondary btn-sm" onclick="setSingleTesterStatus(<?= $tUser['id'] ?>, 'rejected')">Reject</button>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteSingleTester(<?= $tUser['id'] ?>)">Delete</button>
                                                        </div>
                                                        <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                                            <button type="button" class="btn btn-warning btn-sm" style="font-size: 11px; padding: 2px 6px;" title="Instantly complete today's quota (10/10)" onclick="executeTesterTool(<?= $tUser['id'] ?>, 'admin_complete_tester_today', 'Auto-complete today\'s levels (10/10) for Tester #<?= $tUser['id'] ?>?')">⚡ Pass Today (10/10)</button>
                                                            <button type="button" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 2px 6px; background: #0284c7;" title="Unlock next day immediately without waiting 24 hours" onclick="executeTesterTool(<?= $tUser['id'] ?>, 'admin_unlock_tester_next_day', 'Bypass 24h wait and unlock next day immediately for Tester #<?= $tUser['id'] ?>?')">🔓 Unlock Next Day</button>
                                                            <button type="button" class="btn btn-primary btn-sm" style="font-size: 11px; padding: 2px 6px; background: #6366f1;" title="Instantly complete all 7 days" onclick="executeTesterTool(<?= $tUser['id'] ?>, 'admin_complete_all_tester_days', 'Fast-forward all 7 days and enable payout claim for Tester #<?= $tUser['id'] ?>?')">🚀 Complete 7 Days</button>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Subtab B: Payout Claims Studio -->
            <div id="tester-subtab-payouts" style="display: none;">
                <!-- Metrics Summary Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 20px;">
                    <div class="card" style="margin-bottom: 0; padding: 16px; border-left: 4px solid var(--primary); background: rgba(99, 102, 241, 0.05);">
                        <div style="font-size: 12px; color: var(--text-dim); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">Total Payout Claims</div>
                        <div style="display: flex; align-items: baseline; justify-content: space-between; margin-top: 6px;">
                            <span style="font-size: 24px; font-weight: 800; color: #fff;"><?= count($testerClaims) ?></span>
                            <span style="font-size: 15px; font-weight: 700; color: var(--primary);">₹<?= number_format($totalClaimAmount) ?></span>
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">🎯 <?= count($testerClaims) - $repeatTesterClaimCount ?> First-time | 🔁 <?= $repeatTesterClaimCount ?> Repeat</div>
                    </div>

                    <div class="card" style="margin-bottom: 0; padding: 16px; border-left: 4px solid #f59e0b; background: rgba(245, 158, 11, 0.05);">
                        <div style="font-size: 12px; color: #fbbf24; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">⏳ Pending Payouts</div>
                        <div style="display: flex; align-items: baseline; justify-content: space-between; margin-top: 6px;">
                            <span style="font-size: 24px; font-weight: 800; color: #f59e0b;"><?= $pendingClaimCount ?></span>
                            <span style="font-size: 15px; font-weight: 700; color: #fbbf24;">₹<?= number_format($pendingClaimAmount) ?></span>
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Awaiting verification & transfer</div>
                    </div>

                    <div class="card" style="margin-bottom: 0; padding: 16px; border-left: 4px solid var(--success); background: rgba(16, 185, 129, 0.05);">
                        <div style="font-size: 12px; color: #34d399; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">✅ Paid / Sent</div>
                        <div style="display: flex; align-items: baseline; justify-content: space-between; margin-top: 6px;">
                            <span style="font-size: 24px; font-weight: 800; color: var(--success);"><?= $sentClaimCount ?></span>
                            <span style="font-size: 15px; font-weight: 700; color: #34d399;">₹<?= number_format($sentClaimAmount) ?></span>
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Successfully transferred</div>
                    </div>

                    <div class="card" style="margin-bottom: 0; padding: 16px; border-left: 4px solid var(--danger); background: rgba(239, 68, 68, 0.05);">
                        <div style="font-size: 12px; color: #f87171; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px;">❌ Rejected Claims</div>
                        <div style="display: flex; align-items: baseline; justify-content: space-between; margin-top: 6px;">
                            <span style="font-size: 24px; font-weight: 800; color: var(--danger);"><?= $rejectedClaimCount ?></span>
                            <span style="font-size: 12px; color: var(--text-dim);">Claims denied</span>
                        </div>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Invalid details or violations</div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header" style="flex-wrap: wrap; gap: 14px;">
                        <div class="card-title">
                            <div>
                                <h2>💳 Tester Payout & Reward Claims Studio</h2>
                                <p class="card-desc">Review, search, bulk process, and export payout requests submitted by testers after Day 7.</p>
                            </div>
                        </div>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <!-- Export to CSV Form -->
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                                <input type="hidden" name="action" value="export_tester_claims_csv">
                                <button type="submit" class="btn btn-primary" style="display: flex; align-items: center; gap: 6px; font-size: 13px;">
                                    📥 Export CSV (Excel)
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Filter & Search Toolbar -->
                    <div style="background: var(--bg-input); padding: 14px; border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 16px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; align-items: center;">
                            <div>
                                <input type="text" id="claimSearchInput" placeholder="🔍 Search name, email, phone, UPI..." onkeyup="filterTesterClaims()" style="width: 100%; padding: 8px 12px; font-size: 13px;">
                            </div>
                            <div>
                                <select id="claimStatusFilter" onchange="filterTesterClaims()" style="width: 100%; padding: 8px 12px; font-size: 13px;">
                                    <option value="all">Filter by Status: ALL</option>
                                    <option value="pending" <?= ($pendingClaimCount > 0 ? 'selected' : '') ?>>⏳ Pending Only (<?= $pendingClaimCount ?>)</option>
                                    <option value="sent">✅ Sent / Paid Only (<?= $sentClaimCount ?>)</option>
                                    <option value="rejected">❌ Rejected Only (<?= $rejectedClaimCount ?>)</option>
                                </select>
                            </div>
                            <div>
                                <select id="claimRoundFilter" onchange="filterTesterClaims()" style="width: 100%; padding: 8px 12px; font-size: 13px;">
                                    <option value="all">Filter by Round: ALL</option>
                                    <option value="round1">🎯 Round 1 (1st Reward)</option>
                                    <option value="repeat">🔁 Round 2+ (Repeat Testers)</option>
                                </select>
                            </div>
                            <div>
                                <select id="claimMethodFilter" onchange="filterTesterClaims()" style="width: 100%; padding: 8px 12px; font-size: 13px;">
                                    <option value="all">Filter by Method: ALL</option>
                                    <option value="upi">UPI</option>
                                    <option value="paytm">Paytm</option>
                                    <option value="google play">Google Play</option>
                                    <option value="amazon pay">Amazon Pay</option>
                                </select>
                            </div>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="resetClaimFilters()" style="width: 100%;">Reset Filters</button>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Actions Bar -->
                    <form method="POST" id="bulkClaimForm">
                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                        <input type="hidden" name="action" value="bulk_tester_claim_action">
                        <input type="hidden" name="bulk_action" id="bulkClaimActionInput" value="">

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; background: rgba(255,255,255,0.02); padding: 8px 12px; border-radius: 6px; border: 1px dashed var(--border); flex-wrap: wrap; gap:                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-primary btn-sm" onclick="openBulkAssignVoucherModal()">🎟️ Bulk Assign Vouchers</button>
                                <button type="button" class="btn btn-success btn-sm" onclick="submitClaimBulk('sent')">🟢 Bulk Mark Paid</button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="submitClaimBulk('rejected')">🔴 Bulk Reject</button>
                                <button type="button" class="btn btn-danger btn-sm" onclick="submitClaimBulk('delete')">🗑️ Bulk Delete</button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th style="width: 40px; text-align: center;">
                                            <input type="checkbox" id="selectAllClaimsCheckbox" onclick="toggleSelectAllClaims(this)" style="cursor: pointer;">
                                        </th>
                                        <th>Claim ID</th>
                                        <th>Tester Registration Info</th>
                                        <th>Reward Cycle</th>
                                        <th>Payment Method & Address</th>
                                        <th>Amount</th>
                                        <th>Voucher Code</th>
                                        <th>Claim Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($testerClaims)): ?>
                                        <tr>
                                            <td colspan="10" style="text-align: center; color: var(--text-dim); padding: 30px;">
                                                No tester reward payout claims submitted yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($testerClaims as $claim): ?>
                                            <?php
                                             $cs = $claim['status'];
                                             $cBadge = ($cs === 'sent') ? 'badge-active' : (($cs === 'rejected') ? 'badge-rejected' : 'badge-pending');
                                             $cycleNum = intval($claim['cycle'] ?? 1);
                                             $isRepeat = ($cycleNum > 1);
                                             $methodLower = strtolower(trim($claim['payment_method']));
                                             $searchBlob = strtolower(htmlspecialchars(
                                                 ($claim['name'] ?? '') . ' ' .
                                                 ($claim['email'] ?? '') . ' ' .
                                                 ($claim['mobile'] ?? '') . ' ' .
                                                 ($claim['device_id'] ?? '') . ' ' .
                                                 ($claim['payment_method'] ?? '') . ' ' .
                                                 ($claim['account_details'] ?? '') . ' ' .
                                                 ($claim['voucher_code'] ?? '') . ' ' .
                                                 $claim['status'] . ' round ' . $cycleNum
                                             ));
                                             ?>
                                             <tr class="claim-row" 
                                                 data-search="<?= $searchBlob ?>"
                                                 data-status="<?= $cs ?>"
                                                 data-round="<?= $isRepeat ? 'repeat' : 'round1' ?>"
                                                 data-method="<?= $methodLower ?>">
                                                 <td style="text-align: center;">
                                                     <input type="checkbox" name="claim_ids[]" value="<?= $claim['id'] ?>" class="claim-checkbox" onchange="updateSelectedClaimCount()" style="cursor: pointer;">
                                                 </td>
                                                 <td>
                                                     <strong style="color: #fff; font-size: 14px;">#<?= $claim['id'] ?></strong>
                                                     <?php if (!empty($claim['is_finished'])): ?>
                                                         <div style="margin-top: 4px;"><span class="badge badge-secondary" style="font-size: 10px;">🏁 Ended Tester</span></div>
                                                     <?php endif; ?>
                                                 </td>
                                                 <td>
                                                     <strong style="color: #ffffff; font-size: 14px;"><?= htmlspecialchars($claim['name'] ?: 'Registered Tester') ?></strong>
                                                     <div style="font-size: 12px; margin-top: 2px;">
                                                         <?php if (!empty($claim['email'])): ?>
                                                             <a href="mailto:<?= htmlspecialchars($claim['email']) ?>" style="color: #818cf8; text-decoration: none;" title="Send Email">✉️ <?= htmlspecialchars($claim['email']) ?></a>
                                                         <?php endif; ?>
                                                     </div>
                                                     <div style="font-size: 12px; margin-top: 1px;">
                                                         <?php if (!empty($claim['mobile'])): ?>
                                                             <a href="tel:<?= htmlspecialchars($claim['mobile']) ?>" style="color: #34d399; text-decoration: none;" title="Call / WhatsApp">📱 <?= htmlspecialchars($claim['mobile']) ?></a>
                                                         <?php endif; ?>
                                                     </div>
                                                     <div style="font-size: 11px; color: var(--text-dim); margin-top: 3px;">
                                                         <code><?= htmlspecialchars(substr($claim['device_id'], 0, 16)) ?>...</code>
                                                     </div>
                                                     <div style="margin-top: 6px; display: flex; gap: 4px; flex-direction: column;">
                                                         <button type="button" class="btn btn-primary btn-sm" style="font-size: 11px; padding: 4px 8px; background: linear-gradient(135deg, #6366f1, #8b5cf6); border: 1px solid rgba(139, 92, 246, 0.4); color: #ffffff; font-weight: 700; width: 100%; display: flex; align-items: center; justify-content: center; gap: 5px;" onclick="openUserAuditModal('<?= htmlspecialchars(addslashes($claim['device_id'])) ?>', '<?= htmlspecialchars(addslashes($claim['name'] ?: 'Tester')) ?>', <?= $claim['id'] ?>)">
                                                             🔍 Inspect User & Fraud History
                                                         </button>
                                                         <?php
                                                         $cRevList = $allTesterReviews[$claim['device_id']][$cycleNum] ?? [];
                                                         $cRevCount = count($cRevList);
                                                         ?>
                                                         <?php if ($cRevCount > 0): ?>
                                                             <button type="button" class="btn btn-secondary btn-sm" style="font-size: 10px; padding: 2px 6px; border-color: rgba(139, 92, 246, 0.3); color: #c4b5fd; background: rgba(139, 92, 246, 0.1);" onclick="showAllTesterReviews('<?= htmlspecialchars(addslashes($claim['name'] ?: 'Tester')) ?> - Round <?= $cycleNum ?> Reviews', <?= htmlspecialchars(json_encode($cRevList), ENT_QUOTES, 'UTF-8') ?>)">
                                                                 📝 Quick View <?= $cRevCount ?> Days Reviews
                                                             </button>
                                                         <?php endif; ?>
                                                     </div>
                                                 </td>
                                                 <td>
                                                     <?php if ($cycleNum == 1): ?>
                                                         <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-weight: 700; border: 1px solid rgba(16, 185, 129, 0.3);">
                                                             🎯 1st Reward (Round 1)
                                                         </span>
                                                     <?php else: ?>
                                                         <span class="badge" style="background: rgba(139, 92, 246, 0.2); color: #a78bfa; font-weight: 700; border: 1px solid rgba(139, 92, 246, 0.3);">
                                                             🔁 <?= $cycleNum ?><?= ($cycleNum==2?'nd':($cycleNum==3?'rd':'th')) ?> Reward (Round <?= $cycleNum ?>)
                                                         </span>
                                                     <?php endif; ?>
                                                 </td>
                                                 <td>
                                                     <div style="margin-bottom: 4px;">
                                                         <span class="badge" style="background: rgba(99, 102, 241, 0.25); color: #a5b4fc; font-weight: 700;"><?= strtoupper(htmlspecialchars($claim['payment_method'])) ?></span>
                                                     </div>
                                                     <div style="display: flex; align-items: center; gap: 6px;">
                                                         <code id="claim-account-<?= $claim['id'] ?>" style="font-size: 13px; color: #facc15; background: var(--bg-input); padding: 4px 8px; border-radius: 4px; border: 1px solid var(--border); user-select: all;"><?= htmlspecialchars($claim['account_details']) ?></code>
                                                         <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 11px;" title="Copy to Clipboard" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($claim['account_details'])) ?>', this)">📋 Copy</button>
                                                     </div>
                                                 </td>
                                                 <td>
                                                     <strong style="color: var(--success); font-size: 16px; font-weight: 800;">₹<?= $claim['amount'] ?></strong>
                                                 </td>
                                                 <td>
                                                      <?php 
                                                      $isVoucher = isVoucherMethod($claim['payment_method']); 
                                                      ?>
                                                      <?php if (!empty($claim['voucher_code'])): ?>
                                                          <div style="display: flex; align-items: center; gap: 6px;">
                                                              <code style="font-size: 13px; color: #38bdf8; background: rgba(56, 189, 248, 0.1); padding: 4px 8px; border-radius: 4px; border: 1px dashed rgba(56, 189, 248, 0.3); font-weight: 700; user-select: all;"><?= htmlspecialchars($claim['voucher_code']) ?></code>
                                                              <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 11px;" title="Copy Voucher Code" onclick="copyToClipboard('<?= htmlspecialchars(addslashes($claim['voucher_code'])) ?>', this)">📋 Copy</button>
                                                          </div>
                                                      <?php elseif ($isVoucher): ?>
                                                          <button type="button" class="btn btn-primary btn-sm" style="font-size: 11px; padding: 3px 8px; background: #6366f1;" onclick="openAssignVoucherModal(<?= $claim['id'] ?>, '<?= htmlspecialchars(addslashes($claim['name'] ?: 'Tester')) ?>', '<?= htmlspecialchars(addslashes($claim['payment_method'])) ?>', '<?= htmlspecialchars(addslashes($claim['account_details'])) ?>', <?= $claim['amount'] ?>)">
                                                              🎟️ Assign Code
                                                          </button>
                                                      <?php else: ?>
                                                          <span style="font-size: 11px; color: var(--text-dim); background: rgba(255,255,255,0.04); padding: 4px 8px; border-radius: 4px; border: 1px dashed var(--border); display: inline-block;">
                                                              Direct Cash (UPI/Bank)
                                                          </span>
                                                      <?php endif; ?>
                                                 </td>
                                                 <td style="color: var(--text-dim); font-size: 12px;">
                                                     <div><?= date('d M Y', strtotime($claim['created_at'])) ?></div>
                                                     <div style="font-size: 11px; color: var(--text-muted);"><?= date('h:i A', strtotime($claim['created_at'])) ?></div>
                                                 </td>
                                                 <td>
                                                     <span class="badge <?= $cBadge ?>"><?= strtoupper($cs) ?></span>
                                                 </td>
                                                 <td>
                                                     <div style="display: flex; flex-direction: column; gap: 4px;">
                                                         <?php if ($claim['status'] === 'pending'): ?>
                                                             <button type="button" class="btn btn-success btn-sm" onclick="setSingleClaimStatus(<?= $claim['id'] ?>, 'sent')">Mark Paid (Sent)</button>
                                                             <button type="button" class="btn btn-secondary btn-sm" onclick="setSingleClaimStatus(<?= $claim['id'] ?>, 'rejected')">Reject</button>
                                                         <?php elseif ($claim['status'] === 'sent'): ?>
                                                             <button type="button" class="btn btn-secondary btn-sm" style="font-size: 11px;" onclick="setSingleClaimStatus(<?= $claim['id'] ?>, 'pending')">↩️ Reset to Pending</button>
                                                         <?php elseif ($claim['status'] === 'rejected'): ?>
                                                             <button type="button" class="btn btn-success btn-sm" style="font-size: 11px;" onclick="setSingleClaimStatus(<?= $claim['id'] ?>, 'sent')">Mark Paid</button>
                                                         <?php endif; ?>
                                                         <button type="button" class="btn btn-danger btn-sm" style="font-size: 11px;" onclick="deleteSingleClaim(<?= $claim['id'] ?>)">🗑️ Delete</button>
                                                     </div>
                                                 </td>
                                             </tr>
                                         <?php endforeach; ?>
                                     <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Single Claim Action Form (Hidden) -->
            <form id="singleClaimForm" method="POST" style="display:none;">
                <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                <input type="hidden" name="action" id="singleClaimAction" value="update_tester_claim_status">
                <input type="hidden" name="claim_id" id="singleClaimId" value="0">
                <input type="hidden" name="claim_status" id="singleClaimStatus" value="">
            </form>

            <!-- Subtab C: Program Settings & 7-Day Targets -->
            <div id="tester-subtab-settings" style="display: none;">
                <!-- Master Rules Form -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>⚙️ Tester Master Settings</h2>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                        <input type="hidden" name="action" value="save_tester_settings">

                        <h4 style="margin-bottom: 12px; color: var(--primary);">Program Master Settings</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Instant Approval Mode:</label>
                                <select name="instant_approval">
                                    <option value="1" <?= intval($testerSettings['instant_approval'] ?? 1) === 1 ? 'selected' : '' ?>>ON (Users become Active immediately upon registration)</option>
                                    <option value="0" <?= intval($testerSettings['instant_approval'] ?? 1) === 0 ? 'selected' : '' ?>>OFF (Users stay in Pending status until Admin approves)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Total Program Days:</label>
                                <input type="number" name="total_days" value="<?= htmlspecialchars($testerSettings['total_days'] ?? 7) ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Exact Reward Payout Amount (₹): <span style="font-size: 11px; color: var(--success); font-weight: normal;">(Revealed when tester opens Mystery Gift Box)</span></label>
                                <input type="number" name="reward_amount" value="<?= htmlspecialchars($testerSettings['reward_amount'] ?? 150) ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Program Active Status:</label>
                                <select name="is_active">
                                    <option value="1" <?= intval($testerSettings['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>Active (Enabled in Game)</option>
                                    <option value="0" <?= intval($testerSettings['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>Disabled (Hidden in Game)</option>
                                </select>
                            </div>
                        </div>

                        <h4 style="margin-top: 24px; margin-bottom: 12px; color: var(--primary);">In-App Tester Screen Hero Card Content</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Badge Text (Top Pill):</label>
                                <input type="text" name="badge_text" value="<?= htmlspecialchars($testerSettings['badge_text'] ?? 'EARN ₹150 GUARANTEED') ?>" placeholder="e.g. EARN ₹150 GUARANTEED" required>
                            </div>

                            <div class="form-group">
                                <label>Badge Background Color (Hex):</label>
                                <div style="display:flex; gap:10px; align-items:center;">
                                    <input type="color" value="<?= htmlspecialchars($testerSettings['badge_color'] ?? '#06D6A0') ?>" onchange="document.getElementById('badgeColorHex').value = this.value" style="width:45px; height:38px; border:none; border-radius:6px; cursor:pointer;">
                                    <input type="text" name="badge_color" id="badgeColorHex" value="<?= htmlspecialchars($testerSettings['badge_color'] ?? '#06D6A0') ?>" placeholder="#06D6A0" style="flex:1;">
                                </div>
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Card Main Title:</label>
                                <input type="text" name="title" value="<?= htmlspecialchars($testerSettings['title'] ?? 'Join the 7-Day Testing Team') ?>" placeholder="e.g. Join the 7-Day Testing Team" required>
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Card Description Text:</label>
                                <textarea name="description" rows="3" placeholder="Explain the tester rules and reward payout details..."><?= htmlspecialchars($testerSettings['description'] ?? 'Play 10 puzzle levels every day for 7 days, submit short daily bug reviews, and claim direct UPI/Paytm payout on Day 7!') ?></textarea>
                            </div>

                            <!-- 3 Horizontal Perk Items -->
                            <div class="form-group">
                                <label>Perk 1 - Title:</label>
                                <input type="text" name="perk1_title" value="<?= htmlspecialchars($testerSettings['perk1_title'] ?? '10 Levels/Day') ?>" placeholder="10 Levels/Day" required>
                            </div>
                            <div class="form-group">
                                <label>Perk 1 - Subtitle:</label>
                                <input type="text" name="perk1_subtitle" value="<?= htmlspecialchars($testerSettings['perk1_subtitle'] ?? 'Daily Target') ?>" placeholder="Daily Target" required>
                            </div>

                            <div class="form-group">
                                <label>Perk 2 - Title:</label>
                                <input type="text" name="perk2_title" value="<?= htmlspecialchars($testerSettings['perk2_title'] ?? 'Bug Reports') ?>" placeholder="Bug Reports" required>
                            </div>
                            <div class="form-group">
                                <label>Perk 2 - Subtitle:</label>
                                <input type="text" name="perk2_subtitle" value="<?= htmlspecialchars($testerSettings['perk2_subtitle'] ?? 'Quick Review') ?>" placeholder="Quick Review" required>
                            </div>

                            <div class="form-group">
                                <label>Perk 3 - Title:</label>
                                <input type="text" name="perk3_title" value="<?= htmlspecialchars($testerSettings['perk3_title'] ?? 'Instant Cash') ?>" placeholder="Instant Cash" required>
                            </div>
                            <div class="form-group">
                                <label>Perk 3 - Subtitle:</label>
                                <input type="text" name="perk3_subtitle" value="<?= htmlspecialchars($testerSettings['perk3_subtitle'] ?? '₹150 Reward') ?>" placeholder="₹150 Reward" required>
                            </div>
                        </div>

                        <h4 style="margin-top: 28px; margin-bottom: 12px; color: #38bdf8; display: flex; align-items: center; gap: 8px;">
                            <span>📢 Live Payout Marquee & Social Proof Ticker</span>
                            <span style="font-size: 11px; font-weight: normal; background: rgba(56, 189, 248, 0.15); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3); padding: 2px 8px; border-radius: 9999px;">High User Attraction</span>
                        </h4>
                        <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 14px;">
                            Display a continuous, smoothly scrolling live payout stream across app screens. Real approved payouts from database (with privacy-masked names) will automatically stream alongside these demo entries in real-time!
                        </p>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Live Marquee Master Switch:</label>
                                <select name="ticker_enabled">
                                    <option value="1" <?= intval($testerSettings['ticker_enabled'] ?? 1) === 1 ? 'selected' : '' ?>>ENABLED (Show Live Payout Marquee in App)</option>
                                    <option value="0" <?= intval($testerSettings['ticker_enabled'] ?? 1) === 0 ? 'selected' : '' ?>>DISABLED (Hide Everywhere)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Scroll Speed:</label>
                                <select name="ticker_speed">
                                    <option value="20" <?= intval($testerSettings['ticker_speed'] ?? 45) === 20 ? 'selected' : '' ?>>Fast (20s Loop)</option>
                                    <option value="30" <?= intval($testerSettings['ticker_speed'] ?? 45) === 30 ? 'selected' : '' ?>>Normal (30s Loop)</option>
                                    <option value="45" <?= intval($testerSettings['ticker_speed'] ?? 45) === 45 ? 'selected' : '' ?>>Smooth & Calm (45s Loop - Recommended)</option>
                                    <option value="60" <?= intval($testerSettings['ticker_speed'] ?? 45) === 60 ? 'selected' : '' ?>>Slow & Relaxed (60s Loop)</option>
                                    <option value="80" <?= intval($testerSettings['ticker_speed'] ?? 45) === 80 ? 'selected' : '' ?>>Very Slow (80s Loop)</option>
                                    <option value="100" <?= intval($testerSettings['ticker_speed'] ?? 45) === 100 ? 'selected' : '' ?>>Ultra Slow (100s Loop)</option>
                                    <option value="120" <?= intval($testerSettings['ticker_speed'] ?? 45) === 120 ? 'selected' : '' ?>>Super Gentle (120s Loop)</option>
                                </select>
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Display Locations:</label>
                                <div style="display: flex; gap: 16px; flex-wrap: wrap; background: rgba(0,0,0,0.2); padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border-color);">
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                                        <input type="checkbox" name="ticker_show_home" value="1" <?= intval($testerSettings['ticker_show_home'] ?? 1) === 1 ? 'checked' : '' ?>>
                                        🏠 <strong>Home Page</strong> (Below Tester Program Card)
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                                        <input type="checkbox" name="ticker_show_register" value="1" <?= intval($testerSettings['ticker_show_register'] ?? 1) === 1 ? 'checked' : '' ?>>
                                        📝 <strong>Tester Registration Screen</strong> (Top of Form)
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px;">
                                        <input type="checkbox" name="ticker_show_dashboard" value="1" <?= intval($testerSettings['ticker_show_dashboard'] ?? 1) === 1 ? 'checked' : '' ?>>
                                        🚀 <strong>Tester Journey Dashboard</strong> (Top Header)
                                    </label>
                                </div>
                            </div>

                            <div class="form-group" style="grid-column: 1 / -1;">
                                <label>Custom / Demo Live Payout Items (1 item per line):</label>
                                <textarea name="ticker_custom_items" rows="6" placeholder="Name • ₹Amount Method • Time (e.g. Rahul S. • ₹150 UPI • Just now)"><?= htmlspecialchars($testerSettings['ticker_custom_items'] ?? "Rahul S. • ₹150 UPI • Just now\nPriya K. • ₹150 Google Play • 3m ago\nAmit R. • ₹150 Amazon Pay • 7m ago\nVikram J. • ₹150 Paytm • 12m ago\nSneha P. • ₹150 UPI • 18m ago\nRohan M. • ₹150 Google Play • 25m ago\nPooja B. • ₹150 Amazon Pay • 32m ago\nDeepak V. • ₹150 Paytm • 45m ago\nAnanya T. • ₹150 UPI • 1h ago") ?></textarea>
                                <small style="color: var(--text-muted); display: block; margin-top: 4px;">Format: <code>[Name] • ₹[Amount] [Method] • [Time]</code> (UPI, Google Play, Amazon Pay, Paytm supported)</small>
                            </div>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">Save Master Tester Settings & Live Ticker</button>
                        </div>
                    </form>
                </div>

                <!-- 7-Day Requirement Editor -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <div>
                                <h2>📅 Day-wise Target Level Configurator</h2>
                                <p class="card-desc">Configure how many game levels a tester must complete on each specific day.</p>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive" style="margin-bottom: 24px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Target Levels</th>
                                    <th>Day Title</th>
                                    <th>Instructions</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($testerDayConfigs as $dCfg): ?>
                                    <tr>
                                        <td><strong style="color: var(--primary);">Day <?= $dCfg['day_number'] ?></strong></td>
                                        <td><span class="badge badge-active"><?= $dCfg['required_levels'] ?> Levels / Day</span></td>
                                        <td><?= htmlspecialchars($dCfg['title']) ?></td>
                                        <td style="color: var(--text-muted); font-size: 12px;"><?= htmlspecialchars($dCfg['instructions']) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-secondary btn-sm" onclick="populateDayEdit(<?= $dCfg['day_number'] ?>, <?= $dCfg['required_levels'] ?>, '<?= htmlspecialchars(addslashes($dCfg['title'])) ?>', '<?= htmlspecialchars(addslashes($dCfg['instructions'])) ?>')">
                                                ✏️ Edit Day
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Edit Form -->
                    <form method="POST" id="dayEditForm" style="background: var(--bg-input); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border);">
                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                        <input type="hidden" name="action" value="save_tester_day_config">

                        <h4 style="margin-bottom: 14px; color: var(--primary);">➕ Add / Modify Day Configuration</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Day Number (1 to 7):</label>
                                <input type="number" name="day_number" id="day_number_input" min="1" max="30" required>
                            </div>
                            <div class="form-group">
                                <label>Required Level Count:</label>
                                <input type="number" name="required_levels" id="day_levels_input" placeholder="e.g. 10" required>
                            </div>
                            <div class="form-group">
                                <label>Day Title:</label>
                                <input type="text" name="title" id="day_title_input" placeholder="e.g. Day 1: Puzzle Master" required>
                            </div>
                            <div class="form-group">
                                <label>Instructions / Bug Note:</label>
                                <input type="text" name="instructions" id="day_inst_input" placeholder="Complete levels and submit feedback">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-success">Save Day Target</button>
                    </form>
                </div>

                <!-- Payout & Payment Methods Configuration -->
                <div class="card" style="margin-top: 24px;">
                    <div class="card-header">
                        <div class="card-title">
                            <div>
                                <h2>💳 Tester Payout Claim Methods (In-App Popup)</h2>
                                <p class="card-desc">Configure the payment options (UPI, Paytm, Google Play Code, Amazon Pay, etc.) and custom input placeholders shown to users when claiming tester rewards.</p>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive" style="margin-bottom: 24px;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Method Name</th>
                                    <th>Input Placeholder Text</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($testerPayoutMethods)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-dim); padding: 20px;">
                                            No payout methods configured. Default fallback (UPI, Paytm, Google Play, Amazon Pay) will be used.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($testerPayoutMethods as $m): ?>
                                        <tr>
                                            <td><span class="badge" style="background: rgba(99, 102, 241, 0.2); color: #818cf8; font-weight: 700;">#<?= $m['sort_order'] ?></span></td>
                                            <td>
                                                <strong style="color: #fff; font-size: 14px;"><?= htmlspecialchars($m['name']) ?></strong>
                                            </td>
                                            <td>
                                                <code style="font-size: 12px; color: #facc15; background: var(--bg-input); padding: 4px 8px; border-radius: 4px;"><?= htmlspecialchars($m['input_placeholder']) ?></code>
                                            </td>
                                            <td>
                                                <?php if (intval($m['status']) === 1): ?>
                                                    <span class="badge badge-active">ACTIVE</span>
                                                <?php else: ?>
                                                    <span class="badge badge-rejected">DISABLED</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div style="display: flex; gap: 6px;">
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="populatePayoutMethodEdit(<?= $m['id'] ?>, '<?= htmlspecialchars(addslashes($m['name'])) ?>', '<?= htmlspecialchars(addslashes($m['input_placeholder'])) ?>', <?= $m['sort_order'] ?>, <?= $m['status'] ?>)">
                                                        ✏️ Edit
                                                    </button>
                                                    <form method="POST" style="margin:0;">
                                                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                                                        <input type="hidden" name="action" value="toggle_tester_payout_method">
                                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                        <input type="hidden" name="status" value="<?= intval($m['status']) === 1 ? 0 : 1 ?>">
                                                        <button type="submit" class="btn btn-warning btn-sm" style="font-size: 11px;">
                                                            <?= intval($m['status']) === 1 ? 'Disable' : 'Enable' ?>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="margin:0;" onsubmit="return confirm('Delete payment method <?= htmlspecialchars(addslashes($m['name'])) ?>?');">
                                                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                                                        <input type="hidden" name="action" value="delete_tester_payout_method">
                                                        <input type="hidden" name="id" value="<?= $m['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" style="font-size: 11px;">
                                                            Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Add/Edit Method Form -->
                    <form method="POST" id="payoutMethodForm" style="background: var(--bg-input); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border);">
                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                        <input type="hidden" name="action" value="save_tester_payout_method">
                        <input type="hidden" name="id" id="payout_method_id" value="0">

                        <h4 id="payout_method_form_title" style="margin-bottom: 14px; color: var(--primary);">➕ Add / Modify Payment Method</h4>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Method Name (e.g. Google Play, Amazon Pay, UPI, Paytm):</label>
                                <input type="text" name="name" id="payout_method_name" placeholder="e.g. Google Play Code" required>
                            </div>
                            <div class="form-group">
                                <label>Input Placeholder (Instruction shown in App Textbox):</label>
                                <input type="text" name="input_placeholder" id="payout_method_placeholder" placeholder="e.g. Enter Email ID for Google Play Redeem Code" required>
                            </div>
                            <div class="form-group">
                                <label>Display Sort Order (1, 2, 3...):</label>
                                <input type="number" name="sort_order" id="payout_method_sort" value="1" min="0" required>
                            </div>
                            <div class="form-group">
                                <label>Status:</label>
                                <select name="status" id="payout_method_status">
                                    <option value="1">Active (Shown in App Claim Popup)</option>
                                    <option value="0">Disabled (Hidden in App)</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:flex; gap:10px; margin-top:14px;">
                            <button type="submit" id="payout_method_submit_btn" class="btn btn-success">Save Payment Method</button>
                            <button type="button" id="payout_method_cancel_btn" class="btn btn-secondary" onclick="resetPayoutMethodForm()" style="display:none;">Cancel Edit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- TAB 3: BANNERS & SLIDERS -->
        <!-- ========================================================== -->
        <div id="tab-banners" class="tab-panel">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div>
                            <h2>Home Screen Banner Slider Studio</h2>
                            <p class="card-desc">Add and organize carousel banners displayed on the game home screen.</p>
                        </div>
                    </div>
                </div>

                <!-- Existing Banners Table -->
                <div class="table-responsive" style="margin-bottom: 28px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Preview</th>
                                <th>Banner Text</th>
                                <th>Button CTA</th>
                                <th>Action Target</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allBanners)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-dim); padding: 30px;">
                                        No banners created yet. Create your first banner below!
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allBanners as $banner): ?>
                                    <tr>
                                        <td><strong style="color: var(--primary);">#<?= $banner['sort_order'] ?></strong></td>
                                        <td>
                                            <img src="<?= htmlspecialchars($banner['image_url']) ?>" class="banner-thumb" onerror="this.src='https://via.placeholder.com/100x50.png?text=Invalid+Image'">
                                        </td>
                                        <td>
                                            <?= !empty($banner['title']) ? '<strong>' . htmlspecialchars($banner['title']) . '</strong>' : '<em style="color:var(--text-dim); font-size:12px;">None (Clean image)</em>' ?>
                                        </td>
                                        <td>
                                            <?= !empty($banner['button_text']) ? '<span class="badge" style="background:rgba(16,185,129,0.2); color:#34d399;">' . htmlspecialchars($banner['button_text']) . '</span>' : '<em style="color:var(--text-dim); font-size:12px;">No Button</em>' ?>
                                        </td>
                                        <td>
                                            <?php if ($banner['action_type'] === 'tester_program'): ?>
                                                <span class="badge" style="background: rgba(99, 102, 241, 0.2); color: #818cf8;">👥 Tester Screen</span>
                                            <?php elseif ($banner['action_type'] === 'reward_center'): ?>
                                                <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399;">🎁 Reward Center</span>
                                            <?php else: ?>
                                                <span class="badge" style="background: rgba(6, 182, 212, 0.2); color: #22d3ee;">🌐 URL: <?= htmlspecialchars(substr($banner['action_value'] ?? '', 0, 15)) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= intval($banner['status']) === 1 ? 'badge-active' : 'badge-rejected' ?>">
                                                <?= intval($banner['status']) === 1 ? 'Active (Live)' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <!-- Edit Banner -->
                                                <button type="button" class="btn btn-primary btn-sm" onclick='populateBannerEdit(<?= json_encode($banner, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>
                                                    Edit
                                                </button>

                                                <!-- Toggle Active -->
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                                                    <input type="hidden" name="action" value="toggle_banner_status">
                                                    <input type="hidden" name="id" value="<?= $banner['id'] ?>">
                                                    <input type="hidden" name="status" value="<?= intval($banner['status']) === 1 ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm">
                                                        <?= intval($banner['status']) === 1 ? 'Disable' : 'Enable' ?>
                                                    </button>
                                                </form>

                                                <!-- Delete -->
                                                <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this banner?');">
                                                    <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                                                    <input type="hidden" name="action" value="delete_banner">
                                                    <input type="hidden" name="id" value="<?= $banner['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add / Edit Banner Form -->
                <div id="bannerFormCard" style="background: var(--bg-input); padding: 24px; border-radius: var(--radius-md); border: 1px solid var(--border);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px;">
                        <h3 id="banner_form_heading" style="font-size: 16px; margin:0; color: var(--primary);">Add New Slider Banner</h3>
                        <button type="button" id="banner_cancel_btn" class="btn btn-secondary btn-sm" onclick="resetBannerForm()" style="display:none;">✕ Cancel Edit</button>
                    </div>
                    <form method="POST" id="bannerMainForm">
                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                        <input type="hidden" name="action" value="save_banner">
                        <input type="hidden" name="id" id="banner_id_input" value="0">

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Banner Title (Optional - e.g. '7-Day Tester Program'):</label>
                                <input type="text" name="title" id="banner_title_input" placeholder="e.g. Join 7-Day Tester Program (Leave empty if none)">
                            </div>

                            <div class="form-group">
                                <label>Subtitle / Description (Optional):</label>
                                <input type="text" name="description" id="banner_desc_input" placeholder="e.g. Win ₹150 Daily Cash Prizes (Leave empty if none)">
                            </div>

                            <div class="form-group">
                                <label>Button CTA Text (Optional - e.g. 'JOIN >', 'PLAY >', 'OPEN >'):</label>
                                <input type="text" name="button_text" id="banner_btn_text_input" placeholder="e.g. JOIN > or OPEN > (Leave empty to hide button)" value="OPEN >">
                            </div>

                            <div class="form-group">
                                <label>Image URL (Direct image link):</label>
                                <input type="text" name="image_url" id="banner_img_input" placeholder="https://..." oninput="updateBannerPreview(this.value)" required>
                            </div>

                            <div class="form-group">
                                <label>Action Click Behavior:</label>
                                <select name="action_type" id="banner_action_type_input">
                                    <option value="tester_program">Open 7-Day Tester Program Screen</option>
                                    <option value="reward_center">Open Reward Center Screen</option>
                                    <option value="external_url">Open External Website URL</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Action Value (Website URL if selected above):</label>
                                <input type="text" name="action_value" id="banner_action_value_input" placeholder="https://yourwebsite.com">
                            </div>

                            <div class="form-group">
                                <label>Sort Order Position:</label>
                                <input type="number" name="sort_order" id="banner_sort_order_input" value="1" required>
                            </div>

                            <div class="form-group">
                                <label>Live Status:</label>
                                <select name="status" id="banner_status_input">
                                    <option value="1">Active (Visible)</option>
                                    <option value="0">Inactive (Hidden)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Live Preview Box -->
                        <div style="margin-bottom: 20px;">
                            <label style="display:block; margin-bottom: 6px;">Live Image Preview:</label>
                            <img id="bannerPreviewImg" src="https://via.placeholder.com/600x300.png?text=Banner+Image+Preview" style="max-height: 140px; border-radius: 8px; border: 1px solid var(--border);">
                        </div>

                        <div style="display:flex; gap:10px;">
                            <button type="submit" id="banner_submit_btn" class="btn btn-primary">Create Banner Poster</button>
                            <button type="button" id="banner_cancel_btn2" class="btn btn-secondary" onclick="resetBannerForm()" style="display:none;">Cancel Edit</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- TAB 4: REWARDS SYSTEM -->
        <!-- ========================================================== -->
        <div id="tab-rewards" class="tab-panel">
            <!-- Global Switch Card -->
            <div class="toggle-switch-card">
                <div>
                    <h3>🎁 Global Rewards Master Toggle</h3>
                    <p>Current Status: <strong><?= $rewardsEnabled === 1 ? 'ENABLED (Visible to players)' : 'DISABLED (Hidden across all screens)' ?></strong></p>
                </div>
                <form method="POST" style="margin: 0;">
                    <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                    <input type="hidden" name="action" value="toggle_rewards_master">
                    <input type="hidden" name="status" value="<?= $rewardsEnabled === 1 ? '0' : '1' ?>">
                    <button type="submit" class="btn <?= $rewardsEnabled === 1 ? 'btn-success' : 'btn-danger' ?>" style="padding: 10px 20px;">
                        <?= $rewardsEnabled === 1 ? 'Turn Rewards OFF' : 'Turn Rewards ON' ?>
                    </button>
                </form>
            </div>

            <!-- Milestone Levels Table -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div>
                            <h2>Level Milestone Rewards</h2>
                            <p class="card-desc">Configure reward amount per level milestone.</p>
                        </div>
                    </div>
                </div>

                <div class="table-responsive" style="margin-bottom: 24px;">
                    <table>
                        <thead>
                            <tr>
                                <th>Target Level</th>
                                <th>Reward Amount</th>
                                <th>Display Message</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allRewardSettings)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--text-dim); padding: 30px;">
                                        No milestone rewards configured.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allRewardSettings as $rw): ?>
                                    <tr>
                                        <td><strong style="color: var(--primary); font-size: 15px;">Level <?= $rw['required_level'] ?></strong></td>
                                        <td><strong style="color: var(--success); font-size: 15px;">₹<?= $rw['reward_amount'] ?></strong></td>
                                        <td><?= htmlspecialchars($rw['message']) ?></td>
                                        <td>
                                            <span class="badge <?= intval($rw['status']) === 1 ? 'badge-active' : 'badge-rejected' ?>">
                                                <?= intval($rw['status']) === 1 ? 'Active' : 'Disabled' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 8px;">
                                                <form method="POST" style="margin:0;">
                                                    <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                                                    <input type="hidden" name="action" value="toggle_reward_setting">
                                                    <input type="hidden" name="id" value="<?= $rw['id'] ?>">
                                                    <input type="hidden" name="status" value="<?= intval($rw['status']) === 1 ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-secondary btn-sm">
                                                        <?= intval($rw['status']) === 1 ? 'Disable' : 'Enable' ?>
                                                    </button>
                                                </form>

                                                <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this milestone?');">
                                                    <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                                                    <input type="hidden" name="action" value="delete_reward_setting">
                                                    <input type="hidden" name="id" value="<?= $rw['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Add Milestone Form -->
                <div style="background: var(--bg-input); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border);">
                    <h4 style="margin-bottom: 14px; color: var(--primary);">➕ Add / Update Milestone Reward</h4>
                    <form method="POST">
                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                        <input type="hidden" name="action" value="save_reward_setting">

                        <div class="form-grid">
                            <div class="form-group">
                                <label>Required Level:</label>
                                <input type="number" name="required_level" placeholder="e.g. 10" required>
                            </div>
                            <div class="form-group">
                                <label>Reward Amount (₹):</label>
                                <input type="number" name="reward_amount" placeholder="e.g. 50" required>
                            </div>
                            <div class="form-group">
                                <label>Message:</label>
                                <input type="text" name="message" value="Level Milestone Reward!" required>
                            </div>
                            <div class="form-group">
                                <label>Status:</label>
                                <select name="status">
                                    <option value="1">Active</option>
                                    <option value="0">Disabled</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Milestone</button>
                    </form>
                </div>
            </div>

            <!-- In-App Reward Claims -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div>
                            <h2>In-App User Reward Claims (Payout Requests)</h2>
                            <p class="card-desc">Review and approve/reject reward claims from regular players.</p>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Device ID</th>
                                <th>Method</th>
                                <th>Account Details</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allUserClaims)): ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; color: var(--text-dim); padding: 30px;">
                                        No in-app reward claims submitted yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allUserClaims as $claim): ?>
                                    <tr>
                                        <td>#<?= $claim['id'] ?></td>
                                        <td>
                                            <code><?= htmlspecialchars(substr($claim['device_id'], 0, 14)) ?>...</code>
                                            <div style="margin-top: 4px;">
                                                <button type="button" class="btn btn-primary btn-sm" style="font-size: 10px; padding: 2px 6px; background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(99, 102, 241, 0.4); color: #c7d2fe; display: flex; align-items: center; gap: 3px;" onclick="openUserAuditModal('<?= htmlspecialchars(addslashes($claim['device_id'])) ?>', 'User #<?= $claim['id'] ?>')">
                                                    🔍 Audit User
                                                </button>
                                            </div>
                                        </td>
                                        <td><strong style="color: var(--primary);"><?= htmlspecialchars($claim['method']) ?></strong></td>
                                        <td><code style="color: #facc15;"><?= htmlspecialchars($claim['account']) ?></code></td>
                                        <td><strong style="color: var(--success); font-size: 15px;">₹<?= $claim['amount'] ?></strong></td>
                                        <td style="color: var(--text-dim);"><?= htmlspecialchars($claim['created_at']) ?></td>
                                        <td>
                                            <?php
                                            $st = $claim['status'];
                                            $badgeClass = ($st === 'approved' || $st === 'completed') ? 'badge-active' : (($st === 'rejected') ? 'badge-rejected' : 'badge-pending');
                                            ?>
                                            <span class="badge <?= $badgeClass ?>"><?= strtoupper($st) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($claim['status'] === 'pending'): ?>
                                                <div style="display: flex; gap: 6px;">
                                                    <form method="POST" style="margin:0;">
                                                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                                                        <input type="hidden" name="action" value="update_claim_status">
                                                        <input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
                                                        <input type="hidden" name="claim_status" value="completed">
                                                        <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                    </form>
                                                    <form method="POST" style="margin:0;">
                                                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                                                        <input type="hidden" name="action" value="update_claim_status">
                                                        <input type="hidden" name="claim_id" value="<?= $claim['id'] ?>">
                                                        <input type="hidden" name="claim_status" value="rejected">
                                                        <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-dim); font-size: 12px;">Processed</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- TAB 5: CAMPAIGN & POSTBACK TOOLS -->
        <!-- ========================================================== -->
        <div id="tab-tools" class="tab-panel">
            <!-- Tool 1: 1-Click Auto Postback Solver -->
            <div class="card" style="border: 2px solid rgba(99, 102, 241, 0.4);">
                <div class="card-header">
                    <div class="card-title">
                        <div>
                            <h2 style="color: var(--primary);">🚀 1-Click Auto-Complete & Fire Postbacks</h2>
                            <p class="card-desc">Simulates full organic gameplay with 2-minute level spacing and automatically fires milestone postbacks.</p>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                    <input type="hidden" name="action" value="auto_complete_campaign">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Device ID (from attributed campaign):</label>
                            <input type="text" name="device_id" placeholder="e.g. d_12345678" required>
                        </div>
                        <div class="form-group">
                            <label>Start / Completion Timestamp:</label>
                            <input type="datetime-local" name="start_time" id="start_time_auto" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">⚡ Execute Auto-Complete & Fire Postbacks</button>
                </form>
            </div>

            <!-- Tool 2: Sequential History Generator -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <div>
                            <h2>📈 Sequential Level History Generator</h2>
                            <p class="card-desc">Generate realistic gameplay timestamp entries between any custom level range.</p>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                    <input type="hidden" name="action" value="generate_history">

                    <div class="form-grid">
                        <div class="form-group">
                            <label>Device ID:</label>
                            <input type="text" name="device_id" placeholder="e.g. d_12345678" required>
                        </div>
                        <div class="form-group">
                            <label>Start Level:</label>
                            <input type="number" name="start_level" value="1" required>
                        </div>
                        <div class="form-group">
                            <label>Target Level:</label>
                            <input type="number" name="target_level" placeholder="e.g. 15" required>
                        </div>
                        <div class="form-group">
                            <label>Base Start Date/Time:</label>
                            <input type="datetime-local" name="start_time" id="start_time_gen" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-secondary">Generate Level History</button>
                </form>
            </div>

            <!-- Tool 3: Find Device ID & Edit Campaign -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px;">
                <!-- Find Device ID -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <h2>🔍 Find Device ID by UID</h2>
                        </div>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                        <input type="hidden" name="action" value="find_device_id">

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label>Rewardbro User UID:</label>
                            <input type="text" name="rewardbro_uid" placeholder="e.g. rb_uid_123" required>
                        </div>

                        <button type="submit" class="btn btn-secondary">Search Device ID</button>
                    </form>
                </div>

                <!-- Wipe Device Data -->
                <div class="card" style="border: 1px solid rgba(239, 68, 68, 0.4);">
                    <div class="card-header">
                        <div class="card-title">
                            <h2 style="color: var(--danger);">🗑️ Wipe Device Records</h2>
                        </div>
                    </div>

                    <form method="POST" onsubmit="return confirm('Are you sure you want to completely erase all data for this device?');">
                        <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
                        <input type="hidden" name="action" value="delete_device">

                        <div class="form-group" style="margin-bottom: 16px;">
                            <label>Device ID to Wipe:</label>
                            <input type="text" name="device_id" placeholder="e.g. d_12345678" required>
                        </div>

                        <button type="submit" class="btn btn-danger">Completely Erase Device Data</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ========================================================== -->
        <!-- TAB 6: ADS MANAGEMENT & TOPON CONTROLS -->
        <!-- ========================================================== -->
        <div id="tab-ads" class="tab-panel">
            <!-- TopOn Mediation Credentials & Placements Card -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <div class="card-title">
                        <span style="font-size: 24px;">📺</span>
                        <div>
                            <h2>TopOn Ad Mediation & Placement IDs</h2>
                            <p class="card-desc">Configure your TopOn SDK App credentials and Ad Unit Placement IDs.</p>
                        </div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_topon_settings">
                    <input type="hidden" name="redirect_tab" value="ads">

                    <div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));">
                        <div class="form-group">
                            <label>TopOn App ID: <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="topon_app_id" value="<?= htmlspecialchars($currentAppSettings['topon_app_id'] ?? 'h6a6dcd470d298') ?>" required style="font-family: 'JetBrains Mono', monospace; font-weight: 600;">
                        </div>

                        <div class="form-group">
                            <label>TopOn App Key: <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="topon_app_key" value="<?= htmlspecialchars($currentAppSettings['topon_app_key'] ?? 'adc50c45a5db3969578a75b7843f78507') ?>" required style="font-family: 'JetBrains Mono', monospace; font-weight: 600;">
                        </div>

                        <div class="form-group">
                            <label>Splash / App Open Placement ID: <span style="color: #38bdf8;">(ATSplash)</span></label>
                            <input type="text" name="topon_splash_id" value="<?= htmlspecialchars($currentAppSettings['topon_splash_id'] ?? 'b6a6dcd4790001') ?>" required style="font-family: 'JetBrains Mono', monospace; font-weight: 600; color: #38bdf8;">
                        </div>

                        <div class="form-group">
                            <label>Interstitial Placement ID: <span style="color: #a5b4fc;">(ATInterstitial)</span></label>
                            <input type="text" name="topon_interstitial_id" value="<?= htmlspecialchars($currentAppSettings['topon_interstitial_id'] ?? ($currentAppSettings['interstitial_id'] ?? 'b6a6dcd479a322')) ?>" required style="font-family: 'JetBrains Mono', monospace; font-weight: 600; color: #a5b4fc;">
                        </div>

                        <div class="form-group">
                            <label>Rewarded Video Placement ID: <span style="color: #34d399;">(ATRewardVideoAd)</span></label>
                            <input type="text" name="topon_rewarded_id" value="<?= htmlspecialchars($currentAppSettings['topon_rewarded_id'] ?? ($currentAppSettings['rewarded_id'] ?? 'b6a6dcd479a999')) ?>" required style="font-family: 'JetBrains Mono', monospace; font-weight: 600; color: #34d399;">
                        </div>

                        <div class="form-group">
                            <label>Native Ad Placement ID: <span style="color: #facc15;">(ATNative)</span></label>
                            <input type="text" name="topon_native_id" value="<?= htmlspecialchars($currentAppSettings['topon_native_id'] ?? ($currentAppSettings['native_id'] ?? 'b6a6dcd479b111')) ?>" required style="font-family: 'JetBrains Mono', monospace; font-weight: 600; color: #facc15;">
                        </div>

                        <div class="form-group">
                            <label>OneSignal App ID: <span style="color: #ef4444;">(Push Notifications)</span></label>
                            <input type="text" name="onesignal_app_id" placeholder="e.g. b2f7f966-d8cc-11e4-bed1-df8f05be55ba" value="<?= htmlspecialchars($currentAppSettings['onesignal_app_id'] ?? '') ?>" style="font-family: 'JetBrains Mono', monospace; font-weight: 600; color: #f87171;">
                        </div>

                        <div class="form-group">
                            <label>OneSignal REST API Key: <span style="color: #ef4444;">(For Sending Pushes)</span></label>
                            <input type="password" name="onesignal_rest_api_key" placeholder="e.g. os_v2_app_..." value="<?= htmlspecialchars($currentAppSettings['onesignal_rest_api_key'] ?? '') ?>" style="font-family: 'JetBrains Mono', monospace; font-weight: 600; color: #f87171;">
                        </div>

                        <div class="form-group">
                            <label>Native Ads Enabled (In-Game Banners):</label>
                            <select name="native_enabled">
                                <option value="1" <?= intval($currentAppSettings['native_enabled'] ?? 0) === 1 ? 'selected' : '' ?>>🟢 Enabled (Show Native Ads)</option>
                                <option value="0" <?= intval($currentAppSettings['native_enabled'] ?? 0) === 0 ? 'selected' : '' ?>>🔴 Disabled (Hide Native Ads)</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 16px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary">💾 Save TopOn & OneSignal Credentials</button>
                    </div>
                </form>
            </div>

            <!-- Screen-by-Screen Ad Control Matrix (All 11 Touchpoints) -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <span style="font-size: 24px;">🎯</span>
                        <div>
                            <h2>Screen-by-Screen Ad Control Matrix</h2>
                            <p class="card-desc">Control exact ad behavior on each trigger across App Open, In-Game Buttons, Levels, and Tester screens.</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setAllAdControls('none')">🚫 Set All to None</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="setAllAdControls('interstitial')">⚡ Set All Interstitial</button>
                    </div>
                </div>

                <form method="POST" id="adScreenControlsForm">
                    <input type="hidden" name="action" value="save_ad_screen_controls">
                    <input type="hidden" name="redirect_tab" value="ads">

                    <div class="table-responsive">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 1px solid var(--border); text-align: left;">
                                    <th style="padding: 14px 16px; width: 60px;">#</th>
                                    <th style="padding: 14px 16px;">Trigger Touchpoint & Screen</th>
                                    <th style="padding: 14px 16px;">Trigger Key</th>
                                    <th style="padding: 14px 16px;">Configured Format</th>
                                    <th style="padding: 14px 16px; text-align: right;">Select Ad Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $screenTriggersDef = [
                                    'splash_open' => [
                                        'icon' => '🚀',
                                        'title' => 'Splash Screen (App Open)',
                                        'desc' => 'Fires when app cold starts / splash screen finishes loading.',
                                        'type' => 'splash',
                                        'options' => [
                                            'none' => '🚫 None (No Ad / Fast Entry)',
                                            'app_open' => '📱 TopOn Splash / App Open Ad'
                                        ]
                                    ],
                                    'home_tap_to_play' => [
                                        'icon' => '▶️',
                                        'title' => 'Home Screen - Tap to Play',
                                        'desc' => 'Fires when user taps the main "Play" button on Home Screen.',
                                        'type' => 'standard'
                                    ],
                                    'game_reset' => [
                                        'icon' => '🔄',
                                        'title' => 'In-Game - Level Reset Button',
                                        'desc' => 'Fires when user resets current pipe puzzle inside game.',
                                        'type' => 'standard'
                                    ],
                                    'game_hint' => [
                                        'icon' => '💡',
                                        'title' => 'In-Game - Hint Button',
                                        'desc' => 'Fires when user taps Hint button to reveal solution.',
                                        'type' => 'standard'
                                    ],
                                    'game_over_resume' => [
                                        'icon' => '❤️',
                                        'title' => 'Out of Lives - Resume Button',
                                        'desc' => 'Fires when lives expire and user clicks Resume / Continue.',
                                        'type' => 'standard'
                                    ],
                                    'next_level' => [
                                        'icon' => '🏆',
                                        'title' => 'Regular Mode - Next Level Transition',
                                        'desc' => 'Fires upon level victory before starting next puzzle.',
                                        'type' => 'standard'
                                    ],
                                    'tester_form_submit' => [
                                        'icon' => '📝',
                                        'title' => 'Tester Registration - Submit & Join',
                                        'desc' => 'Fires when user registers name/phone/email for tester program.',
                                        'type' => 'standard'
                                    ],
                                    'tester_refresh' => [
                                        'icon' => '🔃',
                                        'title' => 'Tester Journey - Refresh Button',
                                        'desc' => 'Fires when tester taps Refresh icon on 7-day dashboard.',
                                        'type' => 'standard'
                                    ],
                                    'tester_next_level' => [
                                        'icon' => '🧪',
                                        'title' => 'Tester Mode - Next Level Transition',
                                        'desc' => 'Fires upon completing a level while in 7-day tester mode.',
                                        'type' => 'standard'
                                    ],
                                    'tester_rejoin_cycle' => [
                                        'icon' => '🔁',
                                        'title' => 'Tester Circle - Join Next Round (Cycle)',
                                        'desc' => 'Fires when tester restarts for cycle 2, 3, etc.',
                                        'type' => 'standard'
                                    ],
                                    'tester_end_program' => [
                                        'icon' => '🚪',
                                        'title' => 'End Tester Program - "Yes, End" Confirm',
                                        'desc' => 'Fires when user confirms exiting the tester program.',
                                        'type' => 'standard'
                                    ],
                                    'tester_review_submit' => [
                                        'icon' => '📋',
                                        'title' => 'Submit QA Report & Unlock Day Button',
                                        'desc' => 'Fires when tester submits daily review questionnaire to unlock tomorrow.',
                                        'type' => 'standard'
                                    ],
                                    'tester_claim_reward' => [
                                        'icon' => '🎁',
                                        'title' => 'Unwrap Mystery Reward Box Button',
                                        'desc' => 'Fires when tester taps "Tap to Unwrap Box" to reveal and claim ₹150 reward.',
                                        'type' => 'standard'
                                    ],
                                    'tester_dashboard_native' => [
                                        'icon' => '📰',
                                        'title' => 'Tester Dashboard - Native Ad (After Day 2)',
                                        'desc' => 'Renders TopOn Native Banner Ad card between Day 1 & Day 2 in Journey list.',
                                        'type' => 'native'
                                    ]
                                ];

                                $i = 1;
                                foreach ($screenTriggersDef as $key => $meta):
                                    $currentVal = $adControlMap[$key] ?? 'none';
                                ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.04); transition: background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.02)'" onmouseout="this.style.background='transparent'">
                                    <td style="padding: 14px 16px; font-weight: 700; color: var(--text-dim);"><?= $i++ ?></td>
                                    <td style="padding: 14px 16px;">
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <span style="font-size: 20px;"><?= $meta['icon'] ?></span>
                                            <div>
                                                <div style="font-weight: 700; color: #fff; font-size: 14px;"><?= htmlspecialchars($meta['title']) ?></div>
                                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;"><?= htmlspecialchars($meta['desc']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <code style="background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 4px; color: #a5b4fc; font-size: 12px;"><?= htmlspecialchars($key) ?></code>
                                    </td>
                                    <td style="padding: 14px 16px;">
                                        <?php if ($currentVal === 'none'): ?>
                                            <span class="badge" style="background: rgba(148, 163, 184, 0.15); color: #94a3b8; font-weight: 700;">🚫 NONE (DISABLED)</span>
                                        <?php elseif ($currentVal === 'app_open'): ?>
                                            <span class="badge" style="background: rgba(56, 189, 248, 0.2); color: #38bdf8; font-weight: 700;">📱 APP OPEN (SPLASH)</span>
                                        <?php elseif ($currentVal === 'interstitial'): ?>
                                            <span class="badge" style="background: rgba(99, 102, 241, 0.2); color: #a5b4fc; font-weight: 700;">⚡ INTERSTITIAL</span>
                                        <?php elseif ($currentVal === 'rewarded'): ?>
                                            <span class="badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; font-weight: 700;">🎁 REWARDED VIDEO</span>
                                        <?php elseif ($currentVal === 'native'): ?>
                                            <span class="badge" style="background: rgba(168, 85, 247, 0.2); color: #c084fc; font-weight: 700;">📰 TOPON NATIVE AD</span>
                                        <?php else: ?>
                                            <span class="badge"><?= htmlspecialchars($currentVal) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 14px 16px; text-align: right;">
                                        <select name="ad_types[<?= htmlspecialchars($key) ?>]" class="ad-control-select" data-screen-type="<?= $meta['type'] ?>" style="width: auto; min-width: 200px; padding: 8px 12px; font-weight: 600; border-radius: 8px; font-size: 13px;">
                                            <?php if ($meta['type'] === 'splash'): ?>
                                                 <option value="none" <?= $currentVal === 'none' ? 'selected' : '' ?>>🚫 None (No Splash Ad)</option>
                                                 <option value="app_open" <?= $currentVal === 'app_open' ? 'selected' : '' ?>>📱 TopOn Splash (App Open)</option>
                                            <?php elseif ($meta['type'] === 'native'): ?>
                                                 <option value="none" <?= $currentVal === 'none' ? 'selected' : '' ?>>🚫 Disabled (No Native Ad)</option>
                                                 <option value="native" <?= $currentVal === 'native' ? 'selected' : '' ?>>📰 Active (Show TopOn Native Ad)</option>
                                            <?php else: ?>
                                                 <option value="none" <?= $currentVal === 'none' ? 'selected' : '' ?>>🚫 None (No Ad)</option>
                                                 <option value="interstitial" <?= $currentVal === 'interstitial' ? 'selected' : '' ?>>⚡ Interstitial Ad</option>
                                                 <option value="rewarded" <?= $currentVal === 'rewarded' ? 'selected' : '' ?>>🎁 Rewarded Video Ad</option>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="padding: 20px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 13px; color: var(--text-muted);">⚡ All settings sync dynamically with connected Android apps on next launch.</span>
                        <button type="submit" class="btn btn-success" style="padding: 12px 24px; font-size: 14px;">🚀 Save All Ad Triggers</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- TAB: PUSH NOTIFICATIONS -->
        <div id="tab-notifications" class="tab-panel">
            <!-- Header Grid: OneSignal Credentials & Status -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <div class="card-title">
                        <span style="font-size: 24px;">🔑</span>
                        <div>
                            <h2>OneSignal API & Credentials Setup</h2>
                            <p class="card-desc">Configure your OneSignal App ID and REST API Key to broadcast push notifications to all installed users.</p>
                        </div>
                    </div>
                    <?php if (!empty($currentAppSettings['onesignal_app_id']) && !empty($currentAppSettings['onesignal_rest_api_key'])): ?>
                        <span class="badge badge-active" style="padding: 6px 14px; font-size: 13px; font-weight: 700;">🟢 OneSignal Ready</span>
                    <?php else: ?>
                        <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); padding: 6px 14px; font-size: 13px; font-weight: 700;">⚠️ Credentials Missing</span>
                    <?php endif; ?>
                </div>

                <form method="POST" style="padding: 20px;">
                    <input type="hidden" name="action" value="save_topon_settings">
                    <input type="hidden" name="redirect_tab" value="notifications">
                    <input type="hidden" name="topon_app_id" value="<?= htmlspecialchars($currentAppSettings['topon_app_id'] ?? '') ?>">
                    <input type="hidden" name="topon_app_key" value="<?= htmlspecialchars($currentAppSettings['topon_app_key'] ?? '') ?>">
                    <input type="hidden" name="topon_splash_id" value="<?= htmlspecialchars($currentAppSettings['topon_splash_id'] ?? '') ?>">
                    <input type="hidden" name="topon_interstitial_id" value="<?= htmlspecialchars($currentAppSettings['topon_interstitial_id'] ?? '') ?>">
                    <input type="hidden" name="topon_rewarded_id" value="<?= htmlspecialchars($currentAppSettings['topon_rewarded_id'] ?? '') ?>">
                    <input type="hidden" name="topon_native_id" value="<?= htmlspecialchars($currentAppSettings['topon_native_id'] ?? '') ?>">
                    <input type="hidden" name="native_enabled" value="<?= intval($currentAppSettings['native_enabled'] ?? 0) ?>">

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 16px;">
                        <div class="form-group">
                            <label>OneSignal App ID: <span style="color: var(--danger);">*</span></label>
                            <input type="text" name="onesignal_app_id" placeholder="e.g. 5eb5a37e-b458-11e3-ac11-000c2940e62c" value="<?= htmlspecialchars($currentAppSettings['onesignal_app_id'] ?? '') ?>" required style="font-family: 'JetBrains Mono', monospace; font-size: 13.5px; font-weight: 600; color: #38bdf8;">
                            <small style="color: var(--text-dim); font-size: 11px; margin-top: 4px; display: block;">Found in OneSignal Dashboard > Settings > Keys & IDs</small>
                        </div>
                        <div class="form-group">
                            <label>OneSignal REST API Key: <span style="color: var(--danger);">*</span></label>
                            <input type="password" name="onesignal_rest_api_key" placeholder="e.g. os_v2_app_... or NWEyN..." value="<?= htmlspecialchars($currentAppSettings['onesignal_rest_api_key'] ?? '') ?>" required style="font-family: 'JetBrains Mono', monospace; font-size: 13.5px; font-weight: 600; color: #a5b4fc;">
                            <small style="color: var(--text-dim); font-size: 11px; margin-top: 4px; display: block;">Keep secret. Used by Admin Panel to trigger pushes via REST API.</small>
                        </div>
                    </div>

                    <div style="margin-top: 16px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary">💾 Update OneSignal Credentials</button>
                    </div>
                </form>
            </div>

            <!-- Notification Composer & Live Mockup Grid -->
            <div style="display: grid; grid-template-columns: 1.15fr 0.85fr; gap: 24px; margin-bottom: 24px;">
                <!-- Left: Notification Composer Form -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <span style="font-size: 24px;">📢</span>
                            <div>
                                <h2>Broadcast Push Notification</h2>
                                <p class="card-desc">Send rich push notification with big image, action buttons & deep links to all players.</p>
                            </div>
                        </div>
                    </div>

                    <div style="padding: 20px;">
                        <!-- Quick Template Selector -->
                        <div style="margin-bottom: 20px; background: rgba(99, 102, 241, 0.08); border: 1px dashed rgba(99, 102, 241, 0.3); border-radius: 12px; padding: 14px;">
                            <span style="font-size: 12px; font-weight: 700; color: #a5b4fc; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px;">⚡ Quick Message Presets:</span>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="applyNotifPreset('tester_reminder')" style="font-size: 12px;">⏰ Tester Daily Reminder</button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="applyNotifPreset('reward_claim')" style="font-size: 12px;">🎁 ₹150 Reward Waiting</button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="applyNotifPreset('new_challenge')" style="font-size: 12px;">🏹 New Levels Added</button>
                                <button type="button" class="btn btn-secondary btn-sm" onclick="applyNotifPreset('streak')" style="font-size: 12px;">🔥 Don't Break Your Streak</button>
                            </div>
                        </div>

                        <form method="POST" id="pushNotificationForm" onsubmit="return confirm('🚀 Are you sure you want to broadcast this Push Notification to ALL subscribed users?');">
                            <input type="hidden" name="action" value="send_push_notification">
                            <input type="hidden" name="redirect_tab" value="notifications">

                            <div class="form-group" style="margin-bottom: 16px;">
                                <label style="display: flex; justify-content: space-between;">
                                    <span>Notification Title: <span style="color: var(--danger);">*</span></span>
                                    <span id="titleCharCount" style="font-size: 11px; color: var(--text-dim);">0 / 60</span>
                                </label>
                                <input type="text" name="title" id="notif_title_input" placeholder="e.g. 🎁 Complete Day 3 to Claim ₹150!" required oninput="updateNotifMockup()" style="font-size: 14px; font-weight: 700; color: #fff;">
                            </div>

                            <div class="form-group" style="margin-bottom: 16px;">
                                <label style="display: flex; justify-content: space-between;">
                                    <span>Notification Message / Body: <span style="color: var(--danger);">*</span></span>
                                    <span id="msgCharCount" style="font-size: 11px; color: var(--text-dim);">0 / 180</span>
                                </label>
                                <textarea name="message" id="notif_msg_input" rows="3" placeholder="e.g. Only 10 quick levels left today. Finish them now and request your direct UPI or Play Code payout!" required oninput="updateNotifMockup()" style="font-size: 13.5px;"></textarea>
                            </div>

                            <div class="form-group" style="margin-bottom: 16px;">
                                <label>Big Banner / Image URL (Optional):</label>
                                <input type="url" name="image_url" id="notif_img_input" placeholder="https://images.unsplash.com/... or your CDN URL" oninput="updateNotifMockup()" style="font-size: 13px;">
                                <small style="color: var(--text-dim); font-size: 11px; margin-top: 4px; display: block;">Displays as a full-width rich banner inside expanded Android notifications (16:9 or 2:1 recommended).</small>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 16px;">
                                <div class="form-group">
                                    <label>Target Action / Screen:</label>
                                    <select name="action_type" id="notif_action_type_input" onchange="onNotifActionTypeChange()">
                                        <option value="open_app">📱 Normal Launch (Home Screen)</option>
                                        <option value="tester_program">👥 Open 7-Day Tester Program</option>
                                        <option value="reward_center">🎁 Open Reward Claim Center</option>
                                        <option value="open_url">🌐 Open External Web Link</option>
                                    </select>
                                </div>

                                <div class="form-group" id="notif_action_val_group" style="display: none;">
                                    <label>Action Target URL / Value:</label>
                                    <input type="text" name="action_value" id="notif_action_val_input" placeholder="https://..." oninput="updateNotifMockup()">
                                </div>

                                <div class="form-group" id="notif_btn_group">
                                    <label>Action Button Text (Optional):</label>
                                    <input type="text" name="button_text" id="notif_btn_text_input" placeholder="e.g. Claim ₹150 > or Play Now 🏹" oninput="updateNotifMockup()" style="font-weight: 600; color: #38bdf8;">
                                </div>
                            </div>

                            <div style="padding-top: 14px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                                <button type="button" class="btn btn-secondary" onclick="resetNotifForm()">🔄 Clear Form</button>
                                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #6366f1, #a855f7); padding: 12px 28px; font-size: 15px; font-weight: 800; box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);">
                                    🚀 Broadcast Push Now
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Right: Interactive Android Notification Mockup Preview -->
                <div class="card" style="display: flex; flex-direction: column;">
                    <div class="card-header">
                        <div class="card-title">
                            <span style="font-size: 24px;">📱</span>
                            <div>
                                <h2>Live Mobile Preview</h2>
                                <p class="card-desc">Realistic preview of how users will see this notification on their Android device.</p>
                            </div>
                        </div>
                    </div>

                    <div style="padding: 24px; display: flex; flex-direction: column; align-items: center; justify-content: center; flex: 1; background: radial-gradient(circle at top, #0f172a 0%, #060a12 100%);">
                        <!-- Android Phone Notification Mockup Frame -->
                        <div style="width: 100%; max-width: 360px; background: rgba(15, 23, 42, 0.95); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 20px; padding: 16px; box-shadow: 0 20px 40px rgba(0,0,0,0.6), 0 0 30px rgba(99, 102, 241, 0.15);">
                            <!-- Status Bar Mini Header -->
                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #94a3b8; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid rgba(255, 255, 255, 0.06);">
                                <span>11:25</span>
                                <div style="display: flex; gap: 6px; align-items: center;">
                                    <span>🔔</span>
                                    <span>📶</span>
                                    <span>🔋 94%</span>
                                </div>
                            </div>

                            <!-- Notification Shade Item Card -->
                            <div style="background: rgba(30, 41, 59, 0.85); border-radius: 14px; padding: 14px; border: 1px solid rgba(255, 255, 255, 0.08);">
                                <!-- App Header -->
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="width: 24px; height: 24px; border-radius: 6px; background: linear-gradient(135deg, #6366f1, #a855f7); display: flex; align-items: center; justify-content: center; font-size: 13px; box-shadow: 0 2px 8px rgba(99, 102, 241, 0.4);">🏹</div>
                                        <span style="font-size: 12px; font-weight: 700; color: #cbd5e1;">Pipecraze</span>
                                        <span style="font-size: 11px; color: #64748b;">• now</span>
                                    </div>
                                    <span style="font-size: 11px; color: #64748b; cursor: pointer;">⌄</span>
                                </div>

                                <!-- Notification Title -->
                                <div id="mockupTitle" style="font-size: 14px; font-weight: 800; color: #fff; margin-bottom: 4px; line-height: 1.3;">
                                    🎁 Claim Your ₹150 Tester Reward!
                                </div>

                                <!-- Notification Message Body -->
                                <div id="mockupBody" style="font-size: 12.5px; color: #94a3b8; line-height: 1.4; margin-bottom: 10px;">
                                    Complete today's 10 daily levels to keep your test streak active and get instant payment.
                                </div>

                                <!-- Big Image Mockup Preview -->
                                <div id="mockupImgContainer" style="display: none; margin-bottom: 10px; border-radius: 10px; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.1);">
                                    <img id="mockupImg" src="" alt="Notification banner" style="width: 100%; height: 160px; object-fit: cover; display: block;">
                                </div>

                                <!-- Action Buttons Row -->
                                <div id="mockupBtnContainer" style="display: flex; gap: 8px; margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(255, 255, 255, 0.06);">
                                    <button type="button" id="mockupActionBtn" style="background: rgba(99, 102, 241, 0.2); color: #a5b4fc; border: 1px solid rgba(99, 102, 241, 0.4); border-radius: 8px; padding: 6px 14px; font-size: 12px; font-weight: 700; cursor: default;">
                                        Open Game 🏹
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Mini Hint Info -->
                        <div style="margin-top: 16px; font-size: 11.5px; color: var(--text-dim); text-align: center;">
                            💡 Supports Android 8.0 to Android 15 Notification Channels
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sent Notifications History Table -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <span style="font-size: 24px;">📜</span>
                        <div>
                            <h2>Sent Notifications History & Logs</h2>
                            <p class="card-desc">Chronological history of all broadcasted push notifications and delivery status.</p>
                        </div>
                    </div>
                    <span class="badge" style="background: rgba(99, 102, 241, 0.2); color: #818cf8; font-weight: 700; padding: 6px 12px;">Total: <?= count($allPushNotifications) ?> Broadcasts</span>
                </div>

                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--border); text-align: left;">
                                <th style="padding: 14px 16px; width: 60px;"># ID</th>
                                <th style="padding: 14px 16px; width: 80px;">Banner</th>
                                <th style="padding: 14px 16px;">Title & Content</th>
                                <th style="padding: 14px 16px;">Target Action</th>
                                <th style="padding: 14px 16px;">Action Button</th>
                                <th style="padding: 14px 16px; text-align: center;">Recipients</th>
                                <th style="padding: 14px 16px; text-align: center;">Status</th>
                                <th style="padding: 14px 16px;">Sent Date</th>
                                <th style="padding: 14px 16px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allPushNotifications)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-dim);">
                                        <div style="font-size: 32px; margin-bottom: 8px;">🔔</div>
                                        <div>No push notifications sent yet. Use the composer above to broadcast your first message!</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allPushNotifications as $notif): ?>
                                    <tr style="border-bottom: 1px solid rgba(255,255,255,0.03);">
                                        <td style="padding: 14px 16px; font-weight: 700; color: #fff;">#<?= $notif['id'] ?></td>
                                        <td style="padding: 14px 16px;">
                                            <?php if (!empty($notif['image_url'])): ?>
                                                <img src="<?= htmlspecialchars($notif['image_url']) ?>" alt="Banner" style="width: 65px; height: 40px; object-fit: cover; border-radius: 6px; border: 1px solid var(--border);" onerror="this.style.display='none'">
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: var(--text-dim);">No Image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 14px 16px; max-width: 300px;">
                                            <div style="font-weight: 700; color: #fff; font-size: 13.5px;"><?= htmlspecialchars($notif['title']) ?></div>
                                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 3px; line-height: 1.3;"><?= htmlspecialchars($notif['message']) ?></div>
                                            <?php if (!empty($notif['onesignal_id'])): ?>
                                                <div style="font-size: 11px; color: var(--text-dim); margin-top: 4px; font-family: monospace;">ID: <?= htmlspecialchars($notif['onesignal_id']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 14px 16px;">
                                            <span class="badge" style="background: rgba(99, 102, 241, 0.15); color: #a5b4fc; font-weight: 700; font-size: 11px;">
                                                <?= htmlspecialchars($notif['action_type']) ?>
                                            </span>
                                            <?php if (!empty($notif['action_value'])): ?>
                                                <div style="font-size: 11px; color: #38bdf8; margin-top: 3px; font-family: monospace; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    <?= htmlspecialchars($notif['action_value']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 14px 16px;">
                                            <?php if (!empty($notif['button_text'])): ?>
                                                <span class="badge" style="background: rgba(56, 189, 248, 0.15); color: #38bdf8; font-weight: 700; font-size: 11px;">
                                                    <?= htmlspecialchars($notif['button_text']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="font-size: 11px; color: var(--text-dim);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 14px 16px; text-align: center; font-weight: 800; color: #34d399; font-size: 14px;">
                                            <?= number_format(intval($notif['recipients_count'])) ?>
                                        </td>
                                        <td style="padding: 14px 16px; text-align: center;">
                                            <?php if ($notif['status'] === 'sent'): ?>
                                                <span class="badge badge-active" style="font-size: 11px; font-weight: 800;">SENT ✅</span>
                                            <?php else: ?>
                                                <span class="badge" style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); font-size: 11px; font-weight: 800;">FAILED ❌</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 14px 16px; font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                                            <?= htmlspecialchars($notif['created_at']) ?>
                                        </td>
                                        <td style="padding: 14px 16px; text-align: right;">
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete log #<?= $notif['id'] ?>?');">
                                                <input type="hidden" name="action" value="delete_push_notification">
                                                <input type="hidden" name="notif_id" value="<?= $notif['id'] ?>">
                                                <input type="hidden" name="redirect_tab" value="notifications">
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete log">🗑️</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</main>

<!-- Hidden Single Tester Action Form -->
<form id="singleTesterForm" method="POST" style="display:none;">
    <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
    <input type="hidden" name="action" id="singleTesterAction" value="single_tester_action">
    <input type="hidden" name="tester_id" id="singleTesterId">
    <input type="hidden" name="status" id="singleTesterStatus">
    <input type="hidden" name="redirect_tab" value="testers">
</form>

<!-- Daily Bug Feedback Modal -->
<div class="modal-overlay" id="feedbackModal">
    <div class="modal-card" style="max-width: 680px; width: 94%; max-height: 85vh; display: flex; flex-direction: column;">
        <div class="modal-header">
            <h3 id="modalTesterName" style="font-size: 16px; font-weight: 700; color: #fff;">Tester Daily Reviews</h3>
            <button onclick="closeFeedbackModal()" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">✕</button>
        </div>
        <div class="modal-body" style="overflow-y: auto; padding: 16px;">
            <p style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">Submitted Daily Bug Reports & Reviews:</p>
            <div id="modalFeedbackContent" style="display: flex; flex-direction: column; gap: 12px;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeFeedbackModal()">Close</button>
        </div>
    </div>
</div>

<!-- Assign Single Voucher Modal -->
<div class="modal-overlay" id="assignVoucherModal">
    <div class="modal-card" style="max-width: 520px; width: 94%;">
        <div class="modal-header">
            <h3 style="font-size: 16px; font-weight: 700; color: #fff;">🎟️ Assign Voucher Code & Send</h3>
            <button onclick="closeAssignVoucherModal()" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_tester_claim_status">
            <input type="hidden" name="claim_id" id="assignVoucherClaimId" value="0">
            <input type="hidden" name="claim_status" value="sent">
            <input type="hidden" name="redirect_tab" value="testers">

            <div class="modal-body" style="padding: 20px;">
                <div style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 10px; padding: 14px; margin-bottom: 18px;">
                    <div style="font-size: 14px; font-weight: 700; color: #fff;" id="assignVoucherTesterName">Tester Name</div>
                    <div style="font-size: 13px; color: var(--text-muted); margin-top: 4px;">
                        Method: <strong id="assignVoucherMethod" style="color: #a5b4fc;">Google Play</strong> | Amount: <strong id="assignVoucherAmount" style="color: #34d399;">₹50</strong>
                    </div>
                    <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;">
                        Target: <code id="assignVoucherAccount" style="color: #facc15;">user@gmail.com</code>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label>Redeem / Voucher Code: <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="voucher_code" id="assignVoucherCodeInput" placeholder="e.g. GOOGLE-PLAY-ABCD-1234 or AMAZON-CODE" required autofocus style="font-family: 'JetBrains Mono', monospace; font-size: 15px; font-weight: 700; color: #38bdf8;">
                </div>

                <div class="form-group" style="margin-bottom: 8px;">
                    <label>Admin Note (Optional):</label>
                    <input type="text" name="admin_notes" placeholder="e.g. Sent via email / Google Play Card" style="font-size: 13px;">
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeAssignVoucherModal()">Cancel</button>
                <button type="submit" class="btn btn-success">✅ Assign Voucher & Mark Paid</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Assign Vouchers Modal (2-Step Workflow with Live Category Filter & Full Mapping Preview) -->
<div class="modal-overlay" id="bulkAssignVouchersModal">
    <div class="modal-card" style="max-width: 760px; width: 95%; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h3 style="font-size: 17px; font-weight: 700; color: #fff; margin: 0;">📦 Bulk Assign Voucher Codes</h3>
                <p id="bulkModalStepIndicator" style="font-size: 12px; color: var(--text-muted); margin: 3px 0 0 0;">Step 1 of 2: Select Voucher Category & Input Codes</p>
            </div>
            <button type="button" onclick="closeBulkAssignVouchersModal()" style="background:none; border:none; color:var(--text-muted); font-size:22px; cursor:pointer;">✕</button>
        </div>
        
        <form method="POST" id="bulkVoucherMainForm" style="display: flex; flex-direction: column; flex: 1; overflow: hidden; margin: 0;">
            <input type="hidden" name="action" value="bulk_assign_vouchers">
            <input type="hidden" name="bulk_voucher_claim_ids" id="bulkVoucherClaimIdsInput" value="">
            <input type="hidden" name="redirect_tab" value="testers">

            <div class="modal-body" style="padding: 20px; overflow-y: auto; flex: 1;">
                <!-- STEP 1: CATEGORY & INPUT -->
                <div id="bulkVoucherStep1">
                    <div style="background: var(--bg-input); border: 1px solid var(--border); border-radius: 12px; padding: 16px; margin-bottom: 18px;">
                        <div class="form-group" style="margin-bottom: 12px;">
                            <label style="font-size: 13.5px; font-weight: 700; color: #fff; display: flex; align-items: center; justify-content: space-between;">
                                <span>🎯 Select Voucher Category / Method:</span>
                                <span style="font-size: 11px; font-weight: normal; color: #a5b4fc; background: rgba(99, 102, 241, 0.15); padding: 2px 6px; border-radius: 4px;">Excludes UPI / Direct Cash</span>
                            </label>
                            <select id="bulkVoucherCategorySelect" onchange="onBulkVoucherCategoryChange()" style="width: 100%; padding: 10px 14px; font-size: 14px; background: #0b1120; border: 1.5px solid var(--primary); border-radius: 8px; color: #fff; font-weight: 600;">
                                <!-- Dynamic Options Populated in JS -->
                            </select>
                        </div>

                        <!-- Live Category Summary Card -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin-top: 12px;">
                            <div style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 8px; padding: 10px 12px;">
                                <div style="font-size: 11px; color: #a5b4fc; font-weight: 600;">PENDING CLAIMS</div>
                                <div id="bulkVoucherTargetCount" style="font-size: 18px; font-weight: 800; color: #fff; margin-top: 2px;">0</div>
                            </div>
                            <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 8px; padding: 10px 12px;">
                                <div style="font-size: 11px; color: #6ee7b7; font-weight: 600;">TOTAL PAYOUT VALUE</div>
                                <div id="bulkVoucherTargetAmount" style="font-size: 18px; font-weight: 800; color: #34d399; margin-top: 2px;">₹0</div>
                            </div>
                        </div>

                        <!-- Target List Preview Pill -->
                        <div style="margin-top: 12px; font-size: 12px; color: var(--text-muted);">
                            <span style="color: #cbd5e1; font-weight: 600;">Recipient Targets: </span>
                            <span id="bulkVoucherRecipientsSnippet" style="color: #facc15; font-family: monospace;">None</span>
                        </div>
                    </div>

                    <!-- Voucher Code Textarea -->
                    <div class="form-group" style="margin-bottom: 6px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label style="margin: 0; font-weight: 700; color: #fff;">
                                🎟️ Paste Voucher / Redeem Codes (1 per line): <span style="color: var(--danger);">*</span>
                            </label>
                            <span id="bulkVoucherLineCounter" style="font-size: 12px; color: #f59e0b; font-weight: 700; background: rgba(245, 158, 11, 0.1); padding: 2px 8px; border-radius: 4px; border: 1px solid rgba(245, 158, 11, 0.3);">
                                0 codes entered
                            </span>
                        </div>
                        <textarea name="bulk_voucher_codes" id="bulkVoucherCodesTextarea" rows="7" placeholder="GOOGLE-PLAY-CODE-XXXX-1111&#10;GOOGLE-PLAY-CODE-YYYY-2222&#10;GOOGLE-PLAY-CODE-ZZZZ-3333" required style="font-family: 'JetBrains Mono', monospace; font-size: 13.5px; line-height: 1.6; background: #0b1120; border: 1px solid var(--border); border-radius: 8px; color: #38bdf8; font-weight: 600; padding: 10px;" oninput="updateBulkVoucherLineCount()"></textarea>
                        <div id="bulkVoucherCodeStatusNote" style="font-size: 12px; color: var(--text-dim); margin-top: 6px;">
                            💡 Enter unique voucher codes matching the number of pending claims. Next step will show a complete review table before submitting.
                        </div>
                    </div>
                </div>

                <!-- STEP 2: REVIEW & CONFIRMATION PREVIEW -->
                <div id="bulkVoucherStep2" style="display: none;">
                    <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">
                        <div>
                            <strong style="color: #34d399; font-size: 14px;">✅ Preview Voucher Code Mapping</strong>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                                Please review which tester email/account receives each voucher code before confirming.
                            </div>
                        </div>
                        <span id="bulkVoucherPreviewCountBadge" class="badge badge-active" style="font-size: 13px; font-weight: 800; padding: 6px 12px;">0 Claims</span>
                    </div>

                    <!-- Preview Table -->
                    <div class="table-responsive" style="max-height: 320px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                            <thead style="position: sticky; top: 0; background: #131d33; z-index: 1;">
                                <tr>
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid var(--border);"># ID</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid var(--border);">Tester Name</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid var(--border);">Email / Target Account</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid var(--border);">Category</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 1px solid var(--border);">Assigned Voucher Code</th>
                                    <th style="padding: 10px; text-align: center; border-bottom: 1px solid var(--border);">New Status</th>
                                </tr>
                            </thead>
                            <tbody id="bulkVoucherPreviewTbody">
                                <!-- Populated dynamically in JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #0d1527;">
                <button type="button" class="btn btn-secondary" onclick="closeBulkAssignVouchersModal()">Cancel</button>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary" id="bulkVoucherBackBtn" onclick="goToBulkVoucherStep(1)" style="display: none;">⬅️ Back to Edit</button>
                    <button type="button" class="btn btn-primary" id="bulkVoucherNextBtn" onclick="goToBulkVoucherStep(2)">➡️ Next: Preview Mapping</button>
                    <button type="submit" class="btn btn-success" id="bulkVoucherSubmitBtn" style="display: none;">🚀 Confirm & Submit All Vouchers</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Change Admin Password Modal -->
<div class="modal-overlay" id="changePasswordModal">
    <div class="modal-card" style="max-width: 440px; width: 94%;">
        <div class="modal-header">
            <h3 style="font-size: 16px; font-weight: 700; color: #fff;">🔑 Change Admin Password</h3>
            <button onclick="closeChangePasswordModal()" style="background:none; border:none; color:var(--text-muted); font-size:20px; cursor:pointer;">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="change_admin_password">
            <div class="modal-body" style="padding: 20px;">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label>Current Password: <span style="color: var(--danger);">*</span></label>
                    <input type="password" name="current_password" placeholder="••••••••" required>
                </div>
                <div class="form-group" style="margin-bottom: 14px;">
                    <label>New Password (min. 6 characters): <span style="color: var(--danger);">*</span></label>
                    <input type="password" name="new_password" placeholder="••••••••" minlength="6" required>
                </div>
                <div class="form-group" style="margin-bottom: 8px;">
                    <label>Confirm New Password: <span style="color: var(--danger);">*</span></label>
                    <input type="password" name="confirm_password" placeholder="••••••••" minlength="6" required>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 20px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeChangePasswordModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">💾 Save New Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Comprehensive User Profile & Fraud Audit Inspection Modal -->
<div class="modal-overlay" id="userAuditModal" style="z-index: 9999;">
    <div class="modal-card" style="max-width: 950px; width: 96%; max-height: 94vh; display: flex; flex-direction: column; background: #0f172a; border: 1px solid rgba(99, 102, 241, 0.35); box-shadow: 0 25px 60px -12px rgba(0,0,0,0.8), 0 0 35px rgba(99,102,241,0.25);">
        
        <!-- Modal Header -->
        <div class="modal-header" style="padding: 16px 22px; border-bottom: 1px solid rgba(255,255,255,0.08); background: #131d33; display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg, #6366f1, #8b5cf6); display: flex; align-items: center; justify-content: center; font-size: 22px; box-shadow: 0 4px 14px rgba(99,102,241,0.4);">
                    🔍
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <h3 id="auditModalTitle" style="font-size: 18px; font-weight: 800; color: #fff; margin: 0;">User Fraud Audit & Security Inspection</h3>
                        <span id="auditTrustGradeBadge" class="badge" style="font-size: 11.5px; font-weight: 800; padding: 3px 10px; border-radius: 20px;">Analyzing...</span>
                    </div>
                    <p id="auditModalSubtitle" style="font-size: 12px; color: var(--text-muted); margin: 2px 0 0 0;">Device ID: <code id="auditHeaderDeviceId" style="color: #facc15;">loading...</code></p>
                </div>
            </div>
            <button type="button" onclick="closeUserAuditModal()" style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); border-radius: 8px; color:#cbd5e1; font-size:18px; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; cursor:pointer;">✕</button>
        </div>

        <!-- Modal Sub-Navigation Tabs -->
        <div style="display: flex; gap: 4px; padding: 10px 20px 0 20px; background: #0b1120; border-bottom: 1px solid rgba(255,255,255,0.06); overflow-x: auto;">
            <button type="button" class="audit-subtab-btn active" id="btn-audit-tab-overview" onclick="switchAuditSubTab('overview')" style="padding: 8px 16px; font-size: 12.5px; font-weight: 700; border: none; background: transparent; color: #fff; border-bottom: 2px solid var(--primary); cursor: pointer;">
                🛡️ Trust & Profile Summary
            </button>
            <button type="button" class="audit-subtab-btn" id="btn-audit-tab-qa" onclick="switchAuditSubTab('qa')" style="padding: 8px 16px; font-size: 12.5px; font-weight: 600; border: none; background: transparent; color: var(--text-dim); border-bottom: 2px solid transparent; cursor: pointer;">
                📝 7-Day QA Reviews (<span id="auditQaCountBadge">0</span>)
            </button>
            <button type="button" class="audit-subtab-btn" id="btn-audit-tab-gameplay" onclick="switchAuditSubTab('gameplay')" style="padding: 8px 16px; font-size: 12.5px; font-weight: 600; border: none; background: transparent; color: var(--text-dim); border-bottom: 2px solid transparent; cursor: pointer;">
                🎮 Gameplay & Levels History
            </button>
            <button type="button" class="audit-subtab-btn" id="btn-audit-tab-payouts" onclick="switchAuditSubTab('payouts')" style="padding: 8px 16px; font-size: 12.5px; font-weight: 600; border: none; background: transparent; color: var(--text-dim); border-bottom: 2px solid transparent; cursor: pointer;">
                💳 Claims & Payouts (<span id="auditClaimsCountBadge">0</span>)
            </button>
            <button type="button" class="audit-subtab-btn" id="btn-audit-tab-security" onclick="switchAuditSubTab('security')" style="padding: 8px 16px; font-size: 12.5px; font-weight: 600; border: none; background: transparent; color: var(--text-dim); border-bottom: 2px solid transparent; cursor: pointer;">
                🚨 Security & Bypass Logs (<span id="auditSecLogsCountBadge">0</span>)
            </button>
        </div>

        <!-- Modal Body Content -->
        <div class="modal-body" style="padding: 20px; overflow-y: auto; flex: 1;">
            
            <!-- Loading Indicator -->
            <div id="auditLoadingSpinner" style="text-align: center; padding: 50px 20px;">
                <div style="font-size: 36px; animation: spin 1.5s linear infinite; display: inline-block;">⏳</div>
                <div style="font-size: 15px; font-weight: 700; color: #fff; margin-top: 14px;">Fetching User Cryptographic & Fraud Audit...</div>
                <div style="font-size: 12px; color: var(--text-dim); margin-top: 4px;">Analyzing hardware signatures, level logs, and QA timeline</div>
            </div>

            <!-- Main Audit Container (Hidden until loaded) -->
            <div id="auditContentContainer" style="display: none;">
                
                <!-- TAB 1: TRUST & PROFILE SUMMARY -->
                <div id="audit-panel-overview">
                    <!-- Trust Score Alert Box -->
                    <div id="auditTrustScoreBox" style="border-radius: 12px; padding: 16px 20px; margin-bottom: 18px; border: 1px solid rgba(255,255,255,0.1); background: rgba(99,102,241,0.08); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                        <div style="display: flex; align-items: center; gap: 14px;">
                            <div id="auditTrustScoreCircle" style="width: 58px; height: 58px; border-radius: 50%; display: flex; flex-direction: column; align-items: center; justify-content: center; font-weight: 800; font-size: 18px; color: #fff; border: 3px solid #34d399; background: rgba(52, 211, 153, 0.15);">
                                <span id="auditScoreNumber">95%</span>
                            </div>
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <span style="font-size: 16px; font-weight: 800; color: #fff;">Fraud Risk Assessment:</span>
                                    <span id="auditTrustGradeText" style="font-weight: 800; font-size: 15px; color: #34d399;">A+ Genuine User</span>
                                </div>
                                <div id="auditTrustSummaryText" style="font-size: 12px; color: var(--text-muted); margin-top: 3px;">
                                    Passed hardware authenticity, sequential progression, and 7-day review intervals.
                                </div>
                            </div>
                        </div>
                        <div id="auditBanStatusPill">
                            <!-- Populated in JS -->
                        </div>
                    </div>

                    <!-- Fraud Flags & Security Indicators -->
                    <div id="auditFlagsList" style="margin-bottom: 18px;">
                        <!-- Red Flags or Green Checkmarks populated dynamically -->
                    </div>

                    <!-- User Identity & Device Information Grid -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; margin-bottom: 18px;">
                        
                        <!-- Card 1: Registration Profile -->
                        <div style="background: var(--bg-input); border: 1px solid var(--border); border-radius: 10px; padding: 16px;">
                            <div style="font-size: 12px; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
                                👤 Registered Tester Identity
                            </div>
                            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Full Name:</td>
                                    <td style="color: #fff; font-weight: 700; text-align: right;" id="auditUserName">-</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Mobile:</td>
                                    <td style="color: #34d399; font-weight: 700; text-align: right;" id="auditUserMobile">-</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Email:</td>
                                    <td style="color: #818cf8; font-weight: 700; text-align: right;" id="auditUserEmail">-</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Registered On:</td>
                                    <td style="color: #cbd5e1; text-align: right;" id="auditUserRegDate">-</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Registration IP:</td>
                                    <td style="color: #facc15; font-family: monospace; text-align: right;" id="auditUserIp">-</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Card 2: Hardware & Security Gatekeeper -->
                        <div style="background: var(--bg-input); border: 1px solid var(--border); border-radius: 10px; padding: 16px;">
                            <div style="font-size: 12px; font-weight: 700; color: #38bdf8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
                                📱 Hardware & KeyStore Integrity
                            </div>
                            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Package Name:</td>
                                    <td style="color: #38bdf8; font-family: monospace; text-align: right; font-size: 12px;" id="auditPkgName">-</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Google Ad ID (GID):</td>
                                    <td style="color: #cbd5e1; font-family: monospace; text-align: right; font-size: 11px;" id="auditGid">-</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Session Key Status:</td>
                                    <td style="text-align: right;" id="auditKeyStatus">-</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Last Server Ping:</td>
                                    <td style="color: #cbd5e1; text-align: right;" id="auditLastRequest">-</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Campaign Referrer:</td>
                                    <td style="color: #f59e0b; font-weight: 600; text-align: right;" id="auditReferrer">-</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Card 3: Campaign & CPA Attribution -->
                        <div style="background: var(--bg-input); border: 1px solid var(--border); border-radius: 10px; padding: 16px;">
                            <div style="font-size: 12px; font-weight: 700; color: #f59e0b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
                                🎯 Campaign & Attribution Tracker
                            </div>
                            <table style="width: 100%; font-size: 13px; border-collapse: collapse;">
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">RewardBro User ID:</td>
                                    <td style="color: #fff; font-weight: 700; font-family: monospace; text-align: right;" id="auditRbUid">-</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Offer ID:</td>
                                    <td style="color: #f59e0b; font-weight: 700; text-align: right;" id="auditOfferId">-</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Event ID:</td>
                                    <td style="color: #cbd5e1; text-align: right;" id="auditEventId">-</td>
                                </tr>
                                <tr>
                                    <td style="color: var(--text-dim); padding: 4px 0;">Campaign Status:</td>
                                    <td style="text-align: right;" id="auditCampStatus">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: 7-DAY QA REVIEWS -->
                <div id="audit-panel-qa" style="display: none;">
                    <div style="background: rgba(99,102,241,0.06); border: 1px solid rgba(99,102,241,0.2); border-radius: 10px; padding: 12px 16px; margin-bottom: 16px;">
                        <strong style="color: #fff; font-size: 14px;">📝 Daily Bug Reports & QA Questionnaire Verification</strong>
                        <div style="font-size: 12px; color: var(--text-muted); margin-top: 2px;">
                            Verify that the user submitted meaningful bug feedback over 7 real days rather than dummy repetitive text.
                        </div>
                    </div>
                    <div id="auditQaDaysGrid" style="display: flex; flex-direction: column; gap: 10px;">
                        <!-- Populated dynamically in JS -->
                    </div>
                </div>

                <!-- TAB 3: GAMEPLAY & LEVELS HISTORY -->
                <div id="audit-panel-gameplay" style="display: none;">
                    <!-- Gameplay Metrics -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px;">
                        <div style="background: rgba(99, 102, 241, 0.1); border: 1px solid rgba(99, 102, 241, 0.25); border-radius: 8px; padding: 12px;">
                            <div style="font-size: 11px; color: #a5b4fc; font-weight: 700;">CURRENT DB LEVEL</div>
                            <div id="auditCurrentDbLevel" style="font-size: 24px; font-weight: 800; color: #fff; margin-top: 4px;">Level 1</div>
                        </div>
                        <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 8px; padding: 12px;">
                            <div style="font-size: 11px; color: #6ee7b7; font-weight: 700;">UNIQUE LEVEL LOGS</div>
                            <div id="auditUniqueLevelsLogged" style="font-size: 24px; font-weight: 800; color: #34d399; margin-top: 4px;">0</div>
                        </div>
                        <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); border-radius: 8px; padding: 12px;">
                            <div style="font-size: 11px; color: #fde68a; font-weight: 700;">FIRST LEVEL PLAYED</div>
                            <div id="auditFirstLevelTime" style="font-size: 12px; font-weight: 700; color: #f59e0b; margin-top: 6px;">-</div>
                        </div>
                        <div style="background: rgba(56, 189, 248, 0.1); border: 1px solid rgba(56, 189, 248, 0.25); border-radius: 8px; padding: 12px;">
                            <div style="font-size: 11px; color: #bae6fd; font-weight: 700;">LATEST LEVEL PLAYED</div>
                            <div id="auditLastLevelTime" style="font-size: 12px; font-weight: 700; color: #38bdf8; margin-top: 6px;">-</div>
                        </div>
                    </div>

                    <div style="font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 8px;">🎮 Recent Level Checkpoint Records (Anti-Speedhack Audit):</div>
                    <div class="table-responsive" style="max-height: 320px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
                            <thead style="position: sticky; top: 0; background: #131d33; z-index: 1;">
                                <tr>
                                    <th style="padding: 8px 12px; text-align: left;">Level #</th>
                                    <th style="padding: 8px 12px; text-align: left;">Timestamp Logged</th>
                                    <th style="padding: 8px 12px; text-align: left;">IP Address</th>
                                    <th style="padding: 8px 12px; text-align: center;">Verification</th>
                                </tr>
                            </thead>
                            <tbody id="auditRecentLevelsTbody">
                                <!-- Populated dynamically in JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 4: CLAIMS & PAYOUTS -->
                <div id="audit-panel-payouts" style="display: none;">
                    <div class="table-responsive" style="border: 1px solid var(--border); border-radius: 8px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 12.5px;">
                            <thead style="background: #131d33;">
                                <tr>
                                    <th style="padding: 10px; text-align: left;">Claim ID</th>
                                    <th style="padding: 10px; text-align: left;">Program / Cycle</th>
                                    <th style="padding: 10px; text-align: left;">Method & Details</th>
                                    <th style="padding: 10px; text-align: left;">Amount</th>
                                    <th style="padding: 10px; text-align: left;">Voucher / UTR</th>
                                    <th style="padding: 10px; text-align: left;">Date</th>
                                    <th style="padding: 10px; text-align: center;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="auditClaimsTbody">
                                <!-- Populated dynamically in JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 5: SECURITY & BYPASS LOGS -->
                <div id="audit-panel-security" style="display: none;">
                    <!-- Level Bypass Warnings -->
                    <div style="margin-bottom: 18px;">
                        <div style="font-size: 13.5px; font-weight: 700; color: #f87171; margin-bottom: 8px;">⚠️ Level Bypass / Speed Jump Violations:</div>
                        <div id="auditBypassLogsContainer">
                            <!-- Populated in JS -->
                        </div>
                    </div>

                    <!-- Security Gateway Events -->
                    <div style="margin-bottom: 18px;">
                        <div style="font-size: 13.5px; font-weight: 700; color: #38bdf8; margin-bottom: 8px;">🛡️ Security Gateway Event Log:</div>
                        <div id="auditSecLogsContainer">
                            <!-- Populated in JS -->
                        </div>
                    </div>

                    <!-- Postbacks Log -->
                    <div>
                        <div style="font-size: 13.5px; font-weight: 700; color: #a78bfa; margin-bottom: 8px;">📡 CPA Postback Firing History:</div>
                        <div id="auditPostbacksContainer">
                            <!-- Populated in JS -->
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Footer & Direct Actions -->
        <div class="modal-footer" style="padding: 14px 22px; border-top: 1px solid rgba(255,255,255,0.08); background: #0d1527; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <button type="button" class="btn btn-danger btn-sm" id="auditBanDeviceBtn" onclick="banDeviceFromAudit()" style="background: #dc2626; font-weight: 700; display: flex; align-items: center; gap: 5px;">
                    🚫 Ban Device & Reject Payouts
                </button>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeUserAuditModal()">Close Inspection</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Tab Navigation
    function switchTab(tabId) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));

        const targetPanel = document.getElementById('tab-' + tabId);
        if (targetPanel) {
            targetPanel.classList.add('active');
        }

        // Highlight nav item
        const navMap = {
            'dashboard': 0,
            'testers': 1,
            'banners': 2,
            'rewards': 3,
            'tools': 4,
            'ads': 5,
            'notifications': 6
        };
        const navItems = document.querySelectorAll('.nav-item');
        if (navItems[navMap[tabId]]) {
            navItems[navMap[tabId]].classList.add('active');
        }

        const headingMap = {
            'dashboard': 'Admin Dashboard Overview',
            'testers': '7-Day Tester Program Management',
            'banners': 'Home Screen Banner Slider Studio',
            'rewards': 'Milestone Rewards & Claims',
            'tools': 'Campaign & Postback Solvers',
            'ads': 'TopOn Mediation & Screen Ad Controls',
            'notifications': 'Push Notifications & Campaign Broadcast'
        };
        document.getElementById('pageHeading').textContent = headingMap[tabId] || 'Admin Control Studio';
        window.location.hash = tabId;
    }

    // Push Notification Live Mockup & Preset Logic
    function updateNotifMockup() {
        const titleInput = document.getElementById('notif_title_input');
        const msgInput = document.getElementById('notif_msg_input');
        const imgInput = document.getElementById('notif_img_input');
        const btnTextInput = document.getElementById('notif_btn_text_input');

        const titleVal = (titleInput && titleInput.value.trim()) || '🎁 Claim Your ₹150 Tester Reward!';
        const msgVal = (msgInput && msgInput.value.trim()) || "Complete today's 10 daily levels to keep your test streak active and get instant payment.";
        const imgVal = (imgInput && imgInput.value.trim()) || '';
        const btnVal = (btnTextInput && btnTextInput.value.trim()) || 'Open Game 🏹';

        // Update Char Counts
        const titleCounter = document.getElementById('titleCharCount');
        if (titleCounter && titleInput) {
            titleCounter.textContent = `${titleInput.value.length} / 60`;
        }
        const msgCounter = document.getElementById('msgCharCount');
        if (msgCounter && msgInput) {
            msgCounter.textContent = `${msgInput.value.length} / 180`;
        }

        // Update Mockup Elements
        const mTitle = document.getElementById('mockupTitle');
        if (mTitle) mTitle.textContent = titleVal;

        const mBody = document.getElementById('mockupBody');
        if (mBody) mBody.textContent = msgVal;

        const mImgContainer = document.getElementById('mockupImgContainer');
        const mImg = document.getElementById('mockupImg');
        if (mImgContainer && mImg) {
            if (imgVal.length > 8) {
                mImg.src = imgVal;
                mImgContainer.style.display = 'block';
            } else {
                mImgContainer.style.display = 'none';
            }
        }

        const mBtn = document.getElementById('mockupActionBtn');
        if (mBtn) {
            mBtn.textContent = btnVal;
        }
    }

    function onNotifActionTypeChange() {
        const actionType = document.getElementById('notif_action_type_input')?.value || 'open_app';
        const urlGroup = document.getElementById('notif_action_val_group');
        const btnTextInput = document.getElementById('notif_btn_text_input');

        if (urlGroup) {
            urlGroup.style.display = (actionType === 'open_url') ? 'block' : 'none';
        }

        if (btnTextInput && !btnTextInput.value) {
            if (actionType === 'tester_program') btnTextInput.value = 'Open Tester 👥';
            else if (actionType === 'reward_center') btnTextInput.value = 'Claim Cash 🎁';
            else if (actionType === 'open_url') btnTextInput.value = 'Visit Link 🌐';
            else btnTextInput.value = 'Play Now 🏹';
        }
        updateNotifMockup();
    }

    function applyNotifPreset(type) {
        const titleIn = document.getElementById('notif_title_input');
        const msgIn = document.getElementById('notif_msg_input');
        const actionTypeIn = document.getElementById('notif_action_type_input');
        const btnIn = document.getElementById('notif_btn_text_input');

        if (type === 'tester_reminder') {
            if (titleIn) titleIn.value = '⏰ Complete Today\'s 10 Levels!';
            if (msgIn) msgIn.value = 'Your daily tester milestone is waiting! Clear 10 puzzle levels today to stay eligible for your guaranteed ₹150 reward.';
            if (actionTypeIn) actionTypeIn.value = 'tester_program';
            if (btnIn) btnIn.value = 'Open Journey 👥';
        } else if (type === 'reward_claim') {
            if (titleIn) titleIn.value = '🎁 Your ₹150 Reward is Ready!';
            if (msgIn) msgIn.value = 'Congratulations! You have completed all tester requirements. Tap here to enter your UPI ID or redeem voucher code.';
            if (actionTypeIn) actionTypeIn.value = 'reward_center';
            if (btnIn) btnIn.value = 'Claim ₹150 💸';
        } else if (type === 'new_challenge') {
            if (titleIn) titleIn.value = '🏹 50 New Puzzle Levels Unlocked!';
            if (msgIn) msgIn.value = 'Brand new challenging pipe puzzles have been added. Can you solve all of them without losing lives?';
            if (actionTypeIn) actionTypeIn.value = 'open_app';
            if (btnIn) btnIn.value = 'Play Now 🏹';
        } else if (type === 'streak') {
            if (titleIn) titleIn.value = '🔥 Don\'t Break Your Testing Streak!';
            if (msgIn) msgIn.value = 'Only 4 hours left today before daily progress resets. Finish today\'s quota now!';
            if (actionTypeIn) actionTypeIn.value = 'tester_program';
            if (btnIn) btnIn.value = 'Resume Now ⚡';
        }

        onNotifActionTypeChange();
        updateNotifMockup();
    }

    function resetNotifForm() {
        const form = document.getElementById('pushNotificationForm');
        if (form) form.reset();
        onNotifActionTypeChange();
        updateNotifMockup();
    }

    // Set All Ad Controls Bulk Preset Helper
    function setAllAdControls(targetType) {
        document.querySelectorAll('.ad-control-select').forEach(sel => {
            const screenType = sel.getAttribute('data-screen-type');
            if (screenType === 'splash') {
                sel.value = (targetType === 'none') ? 'none' : 'app_open';
            } else {
                if (targetType === 'none') {
                    sel.value = 'none';
                } else if (targetType === 'interstitial') {
                    sel.value = 'interstitial';
                } else if (targetType === 'rewarded') {
                    sel.value = 'rewarded';
                }
            }
        });
    }

    // Tester Subtabs
    function switchTesterSubtab(subId) {
        document.getElementById('tester-subtab-applicants').style.display = (subId === 'applicants') ? 'block' : 'none';
        document.getElementById('tester-subtab-payouts').style.display = (subId === 'payouts') ? 'block' : 'none';
        document.getElementById('tester-subtab-settings').style.display = (subId === 'settings') ? 'block' : 'none';

        document.querySelectorAll('.subtab-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
    }

    // Single tester action
    function setSingleTesterStatus(testerId, status) {
        if (!confirm('Are you sure you want to set tester status to ' + status.toUpperCase() + '?')) return;
        document.getElementById('singleTesterAction').value = 'single_tester_action';
        document.getElementById('singleTesterId').value = testerId;
        document.getElementById('singleTesterStatus').value = status;
        document.getElementById('singleTesterForm').submit();
    }

    function deleteSingleTester(testerId) {
        if (!confirm('Are you sure you want to permanently delete tester #' + testerId + ' and all their progress/claims?')) return;
        document.getElementById('singleTesterAction').value = 'delete_tester_user';
        document.getElementById('singleTesterId').value = testerId;
        document.getElementById('singleTesterStatus').value = '';
        document.getElementById('singleTesterForm').submit();
    }

    function executeTesterTool(testerId, actionName, confirmMsg) {
        if (!confirm(confirmMsg)) return;
        document.getElementById('singleTesterAction').value = actionName;
        document.getElementById('singleTesterId').value = testerId;
        document.getElementById('singleTesterStatus').value = '';
        document.getElementById('singleTesterForm').submit();
    }

    // Bulk tester actions
    function submitBulk(actionType) {
        const checked = document.querySelectorAll('.tester-checkbox:checked');
        if (checked.length === 0) {
            alert('Please select at least one tester.');
            return;
        }
        const confirmMsg = actionType === 'delete' 
            ? 'Are you sure you want to PERMANENTLY DELETE ' + checked.length + ' selected tester(s) and all their associated records?' 
            : 'Apply bulk ' + actionType.toUpperCase() + ' to ' + checked.length + ' tester(s)?';
        if (!confirm(confirmMsg)) return;
        document.getElementById('bulkActionInput').value = actionType;
        document.getElementById('bulkTesterForm').submit();
    }

    // Payout Claims Studio Actions & Tools
    function setSingleClaimStatus(claimId, status) {
        const actionLabel = status === 'sent' ? 'MARK AS PAID (SENT)' : (status === 'rejected' ? 'REJECT' : 'SET TO ' + status.toUpperCase());
        if (!confirm('Are you sure you want to ' + actionLabel + ' for Claim #' + claimId + '?')) return;
        document.getElementById('singleClaimAction').value = 'update_tester_claim_status';
        document.getElementById('singleClaimId').value = claimId;
        document.getElementById('singleClaimStatus').value = status;
        document.getElementById('singleClaimForm').submit();
    }

    function deleteSingleClaim(claimId) {
        if (!confirm('Are you sure you want to PERMANENTLY DELETE Claim #' + claimId + '?')) return;
        document.getElementById('singleClaimAction').value = 'delete_single_tester_claim';
        document.getElementById('singleClaimId').value = claimId;
        document.getElementById('singleClaimStatus').value = '';
        document.getElementById('singleClaimForm').submit();
    }

    function toggleSelectAllClaims(master) {
        const checkboxes = document.querySelectorAll('.claim-checkbox');
        checkboxes.forEach(cb => {
            const row = cb.closest('.claim-row');
            if (row && row.style.display !== 'none') {
                cb.checked = master.checked;
            }
        });
        updateSelectedClaimCount();
    }

    function updateSelectedClaimCount() {
        const count = document.querySelectorAll('.claim-checkbox:checked').length;
        const countEl = document.getElementById('selectedClaimCount');
        if (countEl) countEl.textContent = count;
    }

    function submitClaimBulk(actionType) {
        const checked = document.querySelectorAll('.claim-checkbox:checked');
        if (checked.length === 0) {
            alert('Please select at least one claim.');
            return;
        }
        let confirmMsg = '';
        if (actionType === 'delete') {
            confirmMsg = 'Are you sure you want to PERMANENTLY DELETE ' + checked.length + ' selected claim(s)?';
        } else if (actionType === 'sent') {
            confirmMsg = 'Mark ' + checked.length + ' selected claim(s) as PAID (SENT)?';
        } else if (actionType === 'rejected') {
            confirmMsg = 'REJECT ' + checked.length + ' selected claim(s)?';
        }
        if (!confirm(confirmMsg)) return;

        document.getElementById('bulkClaimActionInput').value = actionType;
        document.getElementById('bulkClaimForm').submit();
    }

    function filterTesterClaims() {
        const query = (document.getElementById('claimSearchInput')?.value || '').toLowerCase().trim();
        const statusFilter = document.getElementById('claimStatusFilter')?.value || 'all';
        const roundFilter = document.getElementById('claimRoundFilter')?.value || 'all';
        const methodFilter = (document.getElementById('claimMethodFilter')?.value || 'all').toLowerCase();

        const rows = document.querySelectorAll('.claim-row');
        rows.forEach(row => {
            const searchData = row.getAttribute('data-search') || '';
            const status = row.getAttribute('data-status') || '';
            const round = row.getAttribute('data-round') || '';
            const method = row.getAttribute('data-method') || '';

            const matchesSearch = !query || searchData.includes(query);
            const matchesStatus = (statusFilter === 'all') || (status === statusFilter);
            const matchesRound = (roundFilter === 'all') || (round === roundFilter);
            const matchesMethod = (methodFilter === 'all') || method.includes(methodFilter);

            if (matchesSearch && matchesStatus && matchesRound && matchesMethod) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
                const cb = row.querySelector('.claim-checkbox');
                if (cb) cb.checked = false;
            }
        });
        updateSelectedClaimCount();
    }

    function resetClaimFilters() {
        if (document.getElementById('claimSearchInput')) document.getElementById('claimSearchInput').value = '';
        if (document.getElementById('claimStatusFilter')) document.getElementById('claimStatusFilter').value = 'all';
        if (document.getElementById('claimRoundFilter')) document.getElementById('claimRoundFilter').value = 'all';
        if (document.getElementById('claimMethodFilter')) document.getElementById('claimMethodFilter').value = 'all';
        filterTesterClaims();
    }

    function copyToClipboard(text, btnElement) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                showCopiedFeedback(btnElement);
            });
        } else {
            const tempInput = document.createElement('input');
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
            showCopiedFeedback(btnElement);
        }
    }

    function showCopiedFeedback(btnElement) {
        if (!btnElement) return;
        const origText = btnElement.innerHTML;
        btnElement.innerHTML = '✅ Copied!';
        btnElement.style.background = '#10b981';
        btnElement.style.color = '#fff';
        setTimeout(() => {
            btnElement.innerHTML = origText;
            btnElement.style.background = '';
            btnElement.style.color = '';
        }, 1500);
    }

    // Multi-Day Reviews Modal
    function showAllTesterReviews(title, reviewsList) {
        document.getElementById('modalTesterName').textContent = '📝 Submitted Reviews: ' + title;
        const container = document.getElementById('modalFeedbackContent');
        container.innerHTML = '';

        if (!reviewsList || reviewsList.length === 0) {
            container.innerHTML = '<div style="text-align:center; color:var(--text-dim); padding:20px;">No reviews recorded yet for this tester.</div>';
        } else {
            reviewsList.forEach(rev => {
                const card = document.createElement('div');
                card.style.cssText = 'background: var(--bg-input); padding: 14px; border-radius: 10px; border: 1px solid var(--border);';
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; flex-wrap: wrap; gap: 6px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span class="badge" style="background: rgba(99, 102, 241, 0.25); color: #a5b4fc; font-weight: 800; font-size: 11px;">DAY ${rev.day}</span>
                            <span style="font-size: 12px; color: #34d399; font-weight: 700;">✅ ${rev.completed}/${rev.target} Levels Cleared</span>
                        </div>
                        <span style="font-size: 11px; color: var(--text-dim);">🕒 ${rev.completed_at || 'N/A'}</span>
                    </div>
                    <div style="font-size: 13px; color: #f1f5f9; line-height: 1.5; background: rgba(0,0,0,0.25); padding: 10px 12px; border-radius: 6px; border-left: 3px solid #8b5cf6; white-space: pre-wrap;">${escapeHtml(rev.feedback || 'No written feedback')}</div>
                `;
                container.appendChild(card);
            });
        }

        document.getElementById('feedbackModal').classList.add('open');
    }

    function showFeedbackModal(name, text) {
        showAllTesterReviews(name, [{
            day: 1,
            target: 10,
            completed: 10,
            feedback: text,
            completed_at: 'Recent'
        }]);
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function closeFeedbackModal() {
        document.getElementById('feedbackModal').classList.remove('open');
    }

    // Day edit populate
    function populateDayEdit(day, levels, title, inst) {
        document.getElementById('day_number_input').value = day;
        document.getElementById('day_levels_input').value = levels;
        document.getElementById('day_title_input').value = title;
        document.getElementById('day_inst_input').value = inst;
        document.getElementById('dayEditForm').scrollIntoView({ behavior: 'smooth' });
    }

    // Banner Preview
    function updateBannerPreview(url) {
        const preview = document.getElementById('bannerPreviewImg');
        if (url && url.trim().length > 5) {
            preview.src = url;
        }
    }

    // Banner Edit & Reset Functions
    function populateBannerEdit(banner) {
        if (!banner) return;
        document.getElementById('banner_id_input').value = banner.id || 0;
        document.getElementById('banner_title_input').value = banner.title || '';
        document.getElementById('banner_desc_input').value = banner.description || '';
        document.getElementById('banner_btn_text_input').value = banner.button_text || '';
        document.getElementById('banner_img_input').value = banner.image_url || '';
        document.getElementById('banner_action_type_input').value = banner.action_type || 'tester_program';
        document.getElementById('banner_action_value_input').value = banner.action_value || '';
        document.getElementById('banner_sort_order_input').value = banner.sort_order || 1;
        document.getElementById('banner_status_input').value = banner.status !== undefined ? banner.status : 1;

        updateBannerPreview(banner.image_url);

        document.getElementById('banner_form_heading').textContent = '✏️ Edit Slider Banner #' + banner.id;
        document.getElementById('banner_submit_btn').textContent = 'Update Banner #' + banner.id;
        document.getElementById('banner_cancel_btn').style.display = 'inline-block';
        if (document.getElementById('banner_cancel_btn2')) {
            document.getElementById('banner_cancel_btn2').style.display = 'inline-block';
        }

        document.getElementById('bannerFormCard').scrollIntoView({ behavior: 'smooth' });
    }

    function resetBannerForm() {
        document.getElementById('banner_id_input').value = '0';
        document.getElementById('bannerMainForm').reset();
        document.getElementById('bannerPreviewImg').src = 'https://via.placeholder.com/600x300.png?text=Banner+Image+Preview';
        document.getElementById('banner_form_heading').textContent = 'Add New Slider Banner';
        document.getElementById('banner_submit_btn').textContent = 'Create Banner Poster';
        document.getElementById('banner_cancel_btn').style.display = 'none';
        if (document.getElementById('banner_cancel_btn2')) {
            document.getElementById('banner_cancel_btn2').style.display = 'none';
        }
    }

    // Payout Method Edit & Reset Functions
    function populatePayoutMethodEdit(id, name, placeholder, sortOrder, status) {
        document.getElementById('payout_method_id').value = id;
        document.getElementById('payout_method_name').value = name;
        document.getElementById('payout_method_placeholder').value = placeholder;
        document.getElementById('payout_method_sort').value = sortOrder;
        document.getElementById('payout_method_status').value = status;

        document.getElementById('payout_method_form_title').textContent = '✏️ Edit Payment Method: ' + name;
        document.getElementById('payout_method_submit_btn').textContent = 'Update Payment Method';
        document.getElementById('payout_method_cancel_btn').style.display = 'inline-block';
        document.getElementById('payoutMethodForm').scrollIntoView({ behavior: 'smooth' });
    }

    function resetPayoutMethodForm() {
        document.getElementById('payout_method_id').value = '0';
        document.getElementById('payoutMethodForm').reset();
        document.getElementById('payout_method_form_title').textContent = '➕ Add / Modify Payment Method';
        document.getElementById('payout_method_submit_btn').textContent = 'Save Payment Method';
        document.getElementById('payout_method_cancel_btn').style.display = 'none';
    }

    // Single Voucher Modal
    function openAssignVoucherModal(claimId, name, method, account, amount) {
        document.getElementById('assignVoucherClaimId').value = claimId;
        document.getElementById('assignVoucherTesterName').textContent = name || 'Registered Tester';
        document.getElementById('assignVoucherMethod').textContent = method || 'Voucher';
        document.getElementById('assignVoucherAmount').textContent = '₹' + amount;
        document.getElementById('assignVoucherAccount').textContent = account || 'N/A';
        document.getElementById('assignVoucherCodeInput').value = '';
        document.getElementById('assignVoucherModal').classList.add('open');
        setTimeout(() => document.getElementById('assignVoucherCodeInput').focus(), 150);
    }

    function closeAssignVoucherModal() {
        document.getElementById('assignVoucherModal').classList.remove('open');
    }

    // Bulk Voucher Modal & Multi-Step Logic
    const pendingClaimsData = <?= json_encode($jsonPendingTesterClaims, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    let bulkTargetClaims = [];

    function openBulkAssignVoucherModal() {
        // 1. Get checked IDs from claims table (if any)
        const checkedBoxes = Array.from(document.querySelectorAll('.claim-checkbox:checked'));
        const checkedIds = checkedBoxes.map(cb => parseInt(cb.value, 10));
        
        // 2. Filter pending voucher claims only
        const voucherPending = pendingClaimsData.filter(c => c.is_voucher);
        const checkedPendingVouchers = pendingClaimsData.filter(c => checkedIds.includes(c.id) && c.is_voucher);
        
        // 3. Build Category Dropdown Options
        const catSelect = document.getElementById('bulkVoucherCategorySelect');
        catSelect.innerHTML = '';

        // Group by payment method
        const methodCounts = {};
        voucherPending.forEach(c => {
            const m = c.method || 'Voucher';
            methodCounts[m] = (methodCounts[m] || 0) + 1;
        });

        if (checkedPendingVouchers.length > 0) {
            const optSel = document.createElement('option');
            optSel.value = 'checked';
            optSel.textContent = `☑️ Selected Pending Voucher Claims (${checkedPendingVouchers.length} selected)`;
            catSelect.appendChild(optSel);
        }

        // Add method options (Google Play, Amazon Pay, etc.)
        for (const [mName, cnt] of Object.entries(methodCounts)) {
            const opt = document.createElement('option');
            opt.value = mName;
            opt.textContent = `🎮 ${mName} (${cnt} Pending)`;
            catSelect.appendChild(opt);
        }

        // Add "All Voucher Categories" option
        if (voucherPending.length > 0) {
            const optAll = document.createElement('option');
            optAll.value = 'all_vouchers';
            optAll.textContent = `🎁 All Voucher Categories (${voucherPending.length} Total Pending)`;
            catSelect.appendChild(optAll);
        }

        if (catSelect.options.length === 0) {
            alert('No pending voucher/gift card claims found! Note: UPI and Paytm claims are direct cash transfers and do not require voucher codes.');
            return;
        }

        // Reset to Step 1 & initialize
        goToBulkVoucherStep(1);
        onBulkVoucherCategoryChange();
        document.getElementById('bulkVoucherCodesTextarea').value = '';
        updateBulkVoucherLineCount();
        
        document.getElementById('bulkAssignVouchersModal').classList.add('open');
        setTimeout(() => document.getElementById('bulkVoucherCodesTextarea').focus(), 150);
    }

    function onBulkVoucherCategoryChange() {
        const catVal = document.getElementById('bulkVoucherCategorySelect').value;
        const checkedIds = Array.from(document.querySelectorAll('.claim-checkbox:checked')).map(cb => parseInt(cb.value, 10));

        if (catVal === 'checked') {
            bulkTargetClaims = pendingClaimsData.filter(c => checkedIds.includes(c.id) && c.is_voucher);
        } else if (catVal === 'all_vouchers') {
            bulkTargetClaims = pendingClaimsData.filter(c => c.is_voucher);
        } else {
            bulkTargetClaims = pendingClaimsData.filter(c => c.method === catVal && c.is_voucher);
        }

        const count = bulkTargetClaims.length;
        const totalAmt = bulkTargetClaims.reduce((acc, c) => acc + c.amount, 0);

        document.getElementById('bulkVoucherTargetCount').textContent = count + ' claim(s)';
        document.getElementById('bulkVoucherTargetAmount').textContent = '₹' + totalAmt;

        const recipients = bulkTargetClaims.map(c => c.account).filter(Boolean);
        document.getElementById('bulkVoucherRecipientsSnippet').textContent = recipients.length > 0 ? (recipients.slice(0, 3).join(', ') + (recipients.length > 3 ? ` +${recipients.length - 3} more` : '')) : 'None';

        document.getElementById('bulkVoucherClaimIdsInput').value = bulkTargetClaims.map(c => c.id).join(',');
        updateBulkVoucherLineCount();
    }

    function updateBulkVoucherLineCount() {
        const text = document.getElementById('bulkVoucherCodesTextarea').value;
        const lines = text.split(/\r\n|\r|\n/).map(l => l.trim()).filter(l => l.length > 0);
        const needed = bulkTargetClaims.length;
        const counterEl = document.getElementById('bulkVoucherLineCounter');
        const nextBtn = document.getElementById('bulkVoucherNextBtn');
        const noteEl = document.getElementById('bulkVoucherCodeStatusNote');

        counterEl.textContent = `${lines.length} code(s) entered (Need: ${needed})`;

        if (lines.length === needed && needed > 0) {
            counterEl.style.color = '#34d399';
            counterEl.style.borderColor = 'rgba(52, 211, 153, 0.4)';
            nextBtn.disabled = false;
            noteEl.innerHTML = `<span style="color: #34d399; font-weight: 700;">✅ Perfect! Exactly ${needed} voucher code(s) entered. Click 'Next: Preview Mapping' to review.</span>`;
        } else if (lines.length > needed && needed > 0) {
            counterEl.style.color = '#38bdf8';
            nextBtn.disabled = false;
            noteEl.innerHTML = `<span style="color: #38bdf8;">ℹ️ You entered ${lines.length} codes for ${needed} claims. The first ${needed} codes will be assigned.</span>`;
        } else {
            counterEl.style.color = '#f59e0b';
            nextBtn.disabled = (needed === 0 || lines.length < needed);
            noteEl.innerHTML = `⚠️ Need <strong>${Math.max(0, needed - lines.length)}</strong> more voucher code(s) (1 code per line).`;
        }
    }

    function goToBulkVoucherStep(step) {
        if (step === 2) {
            const text = document.getElementById('bulkVoucherCodesTextarea').value;
            const lines = text.split(/\r\n|\r|\n/).map(l => l.trim()).filter(l => l.length > 0);
            
            if (bulkTargetClaims.length === 0) {
                alert('No pending claims found in this category!');
                return;
            }
            if (lines.length < bulkTargetClaims.length) {
                alert(`Please provide at least ${bulkTargetClaims.length} voucher codes (1 per line). Currently entered: ${lines.length}.`);
                return;
            }

            // Generate Preview Table
            const tbody = document.getElementById('bulkVoucherPreviewTbody');
            tbody.innerHTML = '';

            bulkTargetClaims.forEach((claim, idx) => {
                const assignedCode = lines[idx] || '';
                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
                tr.innerHTML = `
                    <td style="padding: 10px; font-weight: 700; color: #fff;">#${claim.id}</td>
                    <td style="padding: 10px; font-weight: 600; color: #cbd5e1;">${escapeHtml(claim.name)}</td>
                    <td style="padding: 10px;"><code style="color: #facc15; background: rgba(0,0,0,0.3); padding: 3px 6px; border-radius: 4px; font-size: 12px;">${escapeHtml(claim.account)}</code></td>
                    <td style="padding: 10px;"><span class="badge" style="background: rgba(99, 102, 241, 0.2); color: #a5b4fc; font-weight: 700;">${escapeHtml(claim.method)}</span></td>
                    <td style="padding: 10px;"><code style="color: #38bdf8; background: rgba(56, 189, 248, 0.12); padding: 4px 8px; border-radius: 4px; border: 1px dashed rgba(56, 189, 248, 0.4); font-weight: 700; font-size: 13px; font-family: monospace;">${escapeHtml(assignedCode)}</code></td>
                    <td style="padding: 10px; text-align: center;"><span class="badge badge-active" style="font-size: 11px;">SET AS SENT ✅</span></td>
                `;
                tbody.appendChild(tr);
            });

            document.getElementById('bulkVoucherPreviewCountBadge').textContent = `${bulkTargetClaims.length} Claims Ready`;
            document.getElementById('bulkVoucherStep1').style.display = 'none';
            document.getElementById('bulkVoucherStep2').style.display = 'block';
            document.getElementById('bulkVoucherBackBtn').style.display = 'inline-block';
            document.getElementById('bulkVoucherNextBtn').style.display = 'none';
            document.getElementById('bulkVoucherSubmitBtn').style.display = 'inline-block';
            document.getElementById('bulkModalStepIndicator').textContent = 'Step 2 of 2: Review Mapping & Confirm Distribution';
        } else {
            document.getElementById('bulkVoucherStep1').style.display = 'block';
            document.getElementById('bulkVoucherStep2').style.display = 'none';
            document.getElementById('bulkVoucherBackBtn').style.display = 'none';
            document.getElementById('bulkVoucherNextBtn').style.display = 'inline-block';
            document.getElementById('bulkVoucherSubmitBtn').style.display = 'none';
            document.getElementById('bulkModalStepIndicator').textContent = 'Step 1 of 2: Select Voucher Category & Input Codes';
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    function closeBulkAssignVouchersModal() {
        document.getElementById('bulkAssignVouchersModal').classList.remove('open');
    }

    // ==========================================
    // USER FRAUD AUDIT & DEEP INSPECTION STUDIO
    // ==========================================
    let currentAuditDeviceId = '';

    function openUserAuditModal(deviceId, name, claimId) {
        currentAuditDeviceId = deviceId;
        const modal = document.getElementById('userAuditModal');
        modal.classList.add('open');
        
        // Reset subtab to overview
        switchAuditSubTab('overview');
        
        // Reset views
        document.getElementById('auditLoadingSpinner').style.display = 'block';
        document.getElementById('auditContentContainer').style.display = 'none';
        
        document.getElementById('auditModalTitle').textContent = `🔍 Fraud Audit: ${name || 'User Profile'}`;
        document.getElementById('auditHeaderDeviceId').textContent = deviceId;
        document.getElementById('auditTrustGradeBadge').textContent = 'Analyzing...';
        document.getElementById('auditTrustGradeBadge').style.background = 'rgba(255,255,255,0.1)';
        document.getElementById('auditTrustGradeBadge').style.color = '#fff';

        // AJAX Fetch
        const formData = new FormData();
        formData.append('action', 'get_user_full_audit');
        formData.append('device_id', deviceId);

        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                renderUserAuditData(res.data);
            } else {
                alert('Audit Error: ' + (res.error || 'Failed to fetch audit data'));
                closeUserAuditModal();
            }
        })
        .catch(err => {
            alert('Audit Network Error: ' + err.message);
            closeUserAuditModal();
        });
    }

    function closeUserAuditModal() {
        document.getElementById('userAuditModal').classList.remove('open');
    }

    function switchAuditSubTab(tabName) {
        document.querySelectorAll('.audit-subtab-btn').forEach(b => {
            b.classList.remove('active');
            b.style.color = 'var(--text-dim)';
            b.style.borderBottom = '2px solid transparent';
        });
        const activeBtn = document.getElementById('btn-audit-tab-' + tabName);
        if (activeBtn) {
            activeBtn.classList.add('active');
            activeBtn.style.color = '#fff';
            activeBtn.style.borderBottom = '2px solid var(--primary)';
        }

        ['overview', 'qa', 'gameplay', 'payouts', 'security'].forEach(t => {
            const panel = document.getElementById('audit-panel-' + t);
            if (panel) {
                panel.style.display = (t === tabName) ? 'block' : 'none';
            }
        });
    }

    function renderUserAuditData(data) {
        document.getElementById('auditLoadingSpinner').style.display = 'none';
        document.getElementById('auditContentContainer').style.display = 'block';

        const user = data.user || {};
        const tester = data.tester || null;
        const campaign = data.campaign || null;
        const trust = data.trust || { score: 100, grade: 'A+ (Genuine)', color: '#34d399', flags: [], positives: [] };
        const dailyProg = data.daily_progress || [];
        const claims = data.claims || [];
        const secLogs = data.sec_logs || [];
        const bypassLogs = data.bypass_logs || [];
        const postbacks = data.postbacks || [];
        const levelStats = data.level_stats || {};
        const recentLevels = data.recent_levels || [];

        // 1. Trust Score Header & Alert
        document.getElementById('auditTrustGradeBadge').textContent = trust.grade;
        document.getElementById('auditTrustGradeBadge').style.background = trust.color + '33';
        document.getElementById('auditTrustGradeBadge').style.color = trust.color;
        document.getElementById('auditTrustGradeBadge').style.border = `1px solid ${trust.color}66`;

        document.getElementById('auditScoreNumber').textContent = trust.score + '%';
        document.getElementById('auditTrustScoreCircle').style.borderColor = trust.color;
        document.getElementById('auditTrustScoreCircle').style.background = trust.color + '22';
        document.getElementById('auditTrustGradeText').textContent = trust.grade;
        document.getElementById('auditTrustGradeText').style.color = trust.color;

        // Ban Status Pill
        const isBanned = (parseInt(user.is_banned || 0) === 1);
        const banPill = document.getElementById('auditBanStatusPill');
        if (isBanned) {
            banPill.innerHTML = `<span class="badge badge-rejected" style="font-size: 13px; font-weight: 800; padding: 6px 14px;">🚫 BANNED DEVICE</span>`;
            document.getElementById('auditBanDeviceBtn').style.display = 'none';
        } else {
            banPill.innerHTML = `<span class="badge badge-active" style="font-size: 13px; font-weight: 800; padding: 6px 14px;">🟢 ACTIVE USER</span>`;
            document.getElementById('auditBanDeviceBtn').style.display = 'inline-flex';
        }

        // Flags & Positives
        let flagsHtml = '';
        if (trust.flags && trust.flags.length > 0) {
            flagsHtml += `<div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 10px 14px; margin-bottom: 8px;">
                <div style="font-weight: 700; color: #f87171; font-size: 13px; margin-bottom: 4px;">⚠️ Suspicious Indicators & Fraud Flags Detected:</div>
                <ul style="margin: 0; padding-left: 20px; font-size: 12.5px; color: #fca5a5;">
                    ${trust.flags.map(f => `<li style="margin-top: 3px;">${escapeHtml(f)}</li>`).join('')}
                </ul>
            </div>`;
        }
        if (trust.positives && trust.positives.length > 0) {
            flagsHtml += `<div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 8px; padding: 8px 14px;">
                <div style="font-weight: 700; color: #34d399; font-size: 12.5px;">Verified Authenticity Signals:</div>
                <div style="font-size: 12px; color: #6ee7b7; margin-top: 2px; display: flex; flex-wrap: wrap; gap: 8px;">
                    ${trust.positives.map(p => `<span>${escapeHtml(p)}</span>`).join(' • ')}
                </div>
            </div>`;
        }
        document.getElementById('auditFlagsList').innerHTML = flagsHtml;

        // 2. Identity Card
        document.getElementById('auditUserName').textContent = (tester && tester.name) ? tester.name : (user.name || 'Anonymous Player');
        document.getElementById('auditUserMobile').textContent = (tester && tester.mobile) ? tester.mobile : 'N/A';
        document.getElementById('auditUserEmail').textContent = (tester && tester.email) ? tester.email : 'N/A';
        document.getElementById('auditUserRegDate').textContent = (tester && tester.registered_at) ? tester.registered_at : (user.created_at || 'N/A');
        document.getElementById('auditUserIp').textContent = user.registration_ip || 'N/A';

        // 3. Hardware Card
        document.getElementById('auditPkgName').textContent = user.pkg_name || 'com.pipecraze.puzzle';
        document.getElementById('auditGid').textContent = user.gid || user.dk_gid || 'Not Reported';
        const keyActive = (parseInt(user.key_is_active ?? 1) === 1);
        document.getElementById('auditKeyStatus').innerHTML = keyActive 
            ? '<span class="badge badge-active" style="font-size: 11px;">Active Session</span>'
            : '<span class="badge badge-rejected" style="font-size: 11px;">Keys Disabled</span>';
        document.getElementById('auditLastRequest').textContent = user.last_request_at || 'N/A';
        document.getElementById('auditReferrer').textContent = user.dk_referrer || (campaign && campaign.referrer) || 'Organic Direct';

        // 4. Campaign Card
        document.getElementById('auditRbUid').textContent = (campaign && campaign.rewardbro_uid) || user.dk_uid || user.rewardbro_uid || 'None';
        document.getElementById('auditOfferId').textContent = (campaign && campaign.offer_id) || 'N/A';
        document.getElementById('auditEventId').textContent = (campaign && campaign.event_id) || 'N/A';
        const campSt = (campaign && campaign.status) || 'not_attributed';
        document.getElementById('auditCampStatus').innerHTML = `<span class="badge" style="background: rgba(99,102,241,0.2); color: #a5b4fc; font-weight: 700;">${campSt.toUpperCase()}</span>`;

        // Badges Count
        document.getElementById('auditQaCountBadge').textContent = dailyProg.length;
        document.getElementById('auditClaimsCountBadge').textContent = claims.length;
        document.getElementById('auditSecLogsCountBadge').textContent = secLogs.length + bypassLogs.length;

        // 5. QA Reviews Tab
        const qaGrid = document.getElementById('auditQaDaysGrid');
        qaGrid.innerHTML = '';
        if (dailyProg.length === 0) {
            qaGrid.innerHTML = '<div style="color: var(--text-dim); text-align: center; padding: 25px;">No 7-day QA review submissions found for this user.</div>';
        } else {
            dailyProg.forEach(p => {
                const isCompleted = (p.status === 'completed');
                const card = document.createElement('div');
                card.style.cssText = `background: var(--bg-input); border: 1px solid ${isCompleted ? 'rgba(16, 185, 129, 0.3)' : 'var(--border)'}; border-radius: 8px; padding: 12px 16px;`;
                card.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <div>
                            <strong style="color: #fff; font-size: 13.5px;">Day ${p.day_number}: ${escapeHtml(p.day_title || 'Daily Mission')}</strong>
                            <span class="badge ${isCompleted ? 'badge-active' : 'badge-pending'}" style="margin-left: 8px; font-size: 10.5px;">${(p.status || 'in_progress').toUpperCase()}</span>
                            <span style="font-size: 11px; color: var(--text-dim); margin-left: 6px;">Cycle #${p.cycle || 1}</span>
                        </div>
                        <div style="font-size: 11.5px; color: ${isCompleted ? '#34d399' : 'var(--text-muted)'}; font-weight: 600;">
                            ${p.levels_completed} / ${p.target_levels || p.cfg_req_levels || 10} Levels Cleared
                        </div>
                    </div>
                    <div style="background: rgba(0,0,0,0.25); border-radius: 6px; padding: 8px 12px; font-size: 12.5px; margin-top: 6px; border-left: 3px solid ${isCompleted ? '#34d399' : '#f59e0b'};">
                        <div style="font-size: 11px; color: var(--text-dim); margin-bottom: 3px;">
                            <strong>Submitted Review Feedback:</strong> ${p.completed_at ? `(Completed on ${p.completed_at})` : '(In Progress)'}
                        </div>
                        <div style="color: #f1f5f9; font-style: italic;">
                            "${escapeHtml(p.feedback_text || 'No review text submitted yet.')}"
                        </div>
                    </div>
                `;
                qaGrid.appendChild(card);
            });
        }

        // 6. Gameplay Tab
        document.getElementById('auditCurrentDbLevel').textContent = `Level ${user.current_level || 1}`;
        document.getElementById('auditUniqueLevelsLogged').textContent = `${levelStats.unique_levels || 0} Levels`;
        document.getElementById('auditFirstLevelTime').textContent = levelStats.first_level_time || 'N/A';
        document.getElementById('auditLastLevelTime').textContent = levelStats.last_level_time || 'N/A';

        const recentLevelsTbody = document.getElementById('auditRecentLevelsTbody');
        recentLevelsTbody.innerHTML = '';
        if (recentLevels.length === 0) {
            recentLevelsTbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: var(--text-dim); padding: 18px;">No individual level checkpoint timestamps logged.</td></tr>';
        } else {
            recentLevels.forEach(lvl => {
                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid rgba(255,255,255,0.04)';
                tr.innerHTML = `
                    <td style="padding: 8px 12px; font-weight: 700; color: #fff;">Level ${lvl.level}</td>
                    <td style="padding: 8px 12px; color: #38bdf8; font-family: monospace;">${lvl.created_at}</td>
                    <td style="padding: 8px 12px; color: #facc15; font-family: monospace;">${escapeHtml(lvl.ip_address || '-')}</td>
                    <td style="padding: 8px 12px; text-align: center;"><span class="badge badge-active" style="font-size: 10px;">VALID PASS ✅</span></td>
                `;
                recentLevelsTbody.appendChild(tr);
            });
        }

        // 7. Payout Claims Tab
        const claimsTbody = document.getElementById('auditClaimsTbody');
        claimsTbody.innerHTML = '';
        if (claims.length === 0) {
            claimsTbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-dim); padding: 20px;">No payout requests recorded for this device.</td></tr>';
        } else {
            claims.forEach(c => {
                const cs = c.status || 'pending';
                const cBadge = (cs === 'sent' || cs === 'completed' || cs === 'approved') ? 'badge-active' : ((cs === 'rejected') ? 'badge-rejected' : 'badge-pending');
                const tr = document.createElement('tr');
                tr.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
                tr.innerHTML = `
                    <td style="padding: 10px; font-weight: 700; color: #fff;">#${c.id}</td>
                    <td style="padding: 10px;"><span class="badge" style="background: rgba(99,102,241,0.2); color: #a5b4fc;">${c.type === 'tester' ? `Tester (Round ${c.cycle || 1})` : 'Milestone Reward'}</span></td>
                    <td style="padding: 10px;">
                        <span class="badge" style="background: rgba(255,255,255,0.08); color: #fff; font-weight: 700;">${escapeHtml(c.payment_method)}</span>
                        <div style="font-family: monospace; color: #facc15; font-size: 11.5px; margin-top: 3px;">${escapeHtml(c.account_details)}</div>
                    </td>
                    <td style="padding: 10px; font-weight: 800; color: #34d399; font-size: 14px;">₹${c.amount}</td>
                    <td style="padding: 10px;">
                        ${c.voucher_code ? `<code style="color: #38bdf8; background: rgba(56,189,248,0.12); padding: 2px 6px; border-radius: 4px; font-weight: 700;">${escapeHtml(c.voucher_code)}</code>` : '<span style="color: var(--text-dim); font-size: 11px;">Direct Transfer</span>'}
                        ${c.admin_notes ? `<div style="font-size: 11px; color: var(--text-muted); margin-top: 2px;">Note: ${escapeHtml(c.admin_notes)}</div>` : ''}
                    </td>
                    <td style="padding: 10px; color: var(--text-dim); font-size: 11.5px;">${c.created_at || '-'}</td>
                    <td style="padding: 10px; text-align: center;"><span class="badge ${cBadge}">${cs.toUpperCase()}</span></td>
                `;
                claimsTbody.appendChild(tr);
            });
        }

        // 8. Security Logs Tab
        const bypassCont = document.getElementById('auditBypassLogsContainer');
        if (bypassLogs.length === 0) {
            bypassCont.innerHTML = '<div style="color: #34d399; font-size: 12.5px; background: rgba(16,185,129,0.08); padding: 8px 12px; border-radius: 6px;">✅ Zero level jump / bypass violations recorded for this player.</div>';
        } else {
            let bHtml = '<div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; padding: 10px 14px;">';
            bypassLogs.forEach(b => {
                bHtml += `<div style="font-size: 12px; color: #fca5a5; margin-bottom: 4px;">• <strong>Attempted Level: ${b.attempted_level}</strong> (Current DB was ${b.current_db_level}) on ${b.created_at} from IP ${b.ip_address}</div>`;
            });
            bHtml += '</div>';
            bypassCont.innerHTML = bHtml;
        }

        const secCont = document.getElementById('auditSecLogsContainer');
        if (secLogs.length === 0) {
            secCont.innerHTML = '<div style="color: var(--text-dim); font-size: 12px;">No security incident records.</div>';
        } else {
            let sHtml = '<div style="max-height: 200px; overflow-y: auto; background: var(--bg-input); border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px;">';
            secLogs.forEach(sl => {
                sHtml += `<div style="font-size: 11.5px; color: #cbd5e1; padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,0.04);">
                    <span style="color: #818cf8; font-weight: 700;">${escapeHtml(sl.event_type)}:</span> ${escapeHtml(sl.details || '')} 
                    <span style="color: var(--text-dim); font-size: 10.5px;">(${sl.created_at} - IP: ${sl.ip_address})</span>
                </div>`;
            });
            sHtml += '</div>';
            secCont.innerHTML = sHtml;
        }

        const pbCont = document.getElementById('auditPostbacksContainer');
        if (postbacks.length === 0) {
            pbCont.innerHTML = '<div style="color: var(--text-dim); font-size: 12px;">No CPA postback history for this device.</div>';
        } else {
            let pHtml = '<div style="max-height: 200px; overflow-y: auto; background: var(--bg-input); border: 1px solid var(--border); border-radius: 8px; padding: 8px 12px;">';
            postbacks.forEach(pb => {
                const ok = (parseInt(pb.response_status) >= 200 && parseInt(pb.response_status) < 300);
                pHtml += `<div style="font-size: 11.5px; color: #cbd5e1; padding: 4px 0; border-bottom: 1px solid rgba(255,255,255,0.04);">
                    <span class="badge ${ok ? 'badge-active' : 'badge-rejected'}" style="font-size: 10px;">HTTP ${pb.response_status}</span> 
                    <strong>Level ${pb.level}</strong> (Event: ${escapeHtml(pb.event_id)}) on ${pb.created_at} 
                    <div style="color: var(--text-muted); font-size: 10.5px; font-family: monospace;">Response: ${escapeHtml(pb.response_body || '')}</div>
                </div>`;
            });
            pHtml += '</div>';
            pbCont.innerHTML = pHtml;
        }
    }

    function banDeviceFromAudit() {
        if (!currentAuditDeviceId) return;
        const reason = prompt(`Enter reason for banning device (${currentAuditDeviceId}) and rejecting pending claims:`, 'Fraudulent activity detected during payout audit.');
        if (reason === null) return;

        if (!confirm(`Are you 100% SURE you want to BAN device ${currentAuditDeviceId} and cancel all pending payouts?`)) return;

        const formData = new FormData();
        formData.append('action', 'ban_user_and_device');
        formData.append('device_id', currentAuditDeviceId);
        formData.append('reason', reason);

        fetch('admin.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(res => {
            if (res.success) {
                alert('✅ ' + res.message);
                // Re-open audit to refresh
                openUserAuditModal(currentAuditDeviceId, 'Banned User');
            } else {
                alert('Error banning device: ' + (res.error || 'Failed'));
            }
        })
        .catch(err => alert('Network error: ' + err.message));
    }

    // Change Password Modal
    function openChangePasswordModal() {
        document.getElementById('changePasswordModal').classList.add('open');
    }

    function closeChangePasswordModal() {
        document.getElementById('changePasswordModal').classList.remove('open');
    }

    // Initialization
    document.addEventListener('DOMContentLoaded', function() {
        // 1. Prevent Browser "Confirm Form Resubmission" on Refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }

        // 2. Auto-attach active tab hash to every form submission
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form && form.tagName === 'FORM') {
                const currentTab = window.location.hash.replace('#', '') || 'dashboard';
                let redirectInput = form.querySelector('input[name="redirect_tab"]');
                if (!redirectInput) {
                    redirectInput = document.createElement('input');
                    redirectInput.type = 'hidden';
                    redirectInput.name = 'redirect_tab';
                    form.appendChild(redirectInput);
                }
                redirectInput.value = currentTab;
            }
        });

        // 3. Hash tab restore
        const hash = window.location.hash.replace('#', '');
        if (hash && ['dashboard', 'testers', 'banners', 'rewards', 'tools', 'ads', 'notifications'].includes(hash)) {
            switchTab(hash);
        }

        // Set default datetime
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        const formatted = now.toISOString().slice(0, 16);

        const autoTime = document.getElementById('start_time_auto');
        if (autoTime) autoTime.value = formatted;

        const genTime = document.getElementById('start_time_gen');
        if (genTime) genTime.value = formatted;

        // Select All Checkbox
        const selectAll = document.getElementById('selectAllTesters');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                document.querySelectorAll('.tester-checkbox').forEach(cb => cb.checked = selectAll.checked);
            });
        }

        // Tester Search Filter
        const searchInput = document.getElementById('testerSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const query = e.target.value.toLowerCase().trim();
                document.querySelectorAll('.tester-row').forEach(row => {
                    const searchData = row.getAttribute('data-search') || '';
                    row.style.display = searchData.includes(query) ? '' : 'none';
                });
            });
        }
    });
</script>
</body>
</html>
