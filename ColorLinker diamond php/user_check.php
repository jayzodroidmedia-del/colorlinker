<?php
require_once __DIR__ . '/config.php';

// 1. Security Pin Authentication
$isCli = (php_sapi_name() === 'cli');
$pin = $isCli ? ($argv[1] ?? '') : ($_GET['pin'] ?? '');

if ($pin !== 'mint3') {
    if (!$isCli) {
        header("HTTP/1.1 403 Forbidden");
    }
    exit("Access Denied. Please provide the correct security pin (pin=mint3).\n");
}

// 2. Fetch UID parameter
$uid = $isCli ? ($argv[2] ?? '') : ($_GET['uid'] ?? '');
if (empty($uid)) {
    exit("Error: Please provide a uid (e.g. user_check.php?pin=mint3&uid=sgQo4a3Hu5UGLOWEGp7qWcPrirs1).\n");
}

// Helper function to print key-value status
function printStatus($title, $value, $ok, $details = '') {
    $symbol = $ok ? "✅" : "❌";
    echo "[$symbol] <strong>" . htmlspecialchars($title) . "</strong>: " . htmlspecialchars($value);
    if (!empty($details)) {
        echo " (" . htmlspecialchars($details) . ")";
    }
    echo "<br>\n";
}

if ($isCli) {
    // Strip HTML for CLI
    function htmlspecialchars($str) { return $str; }
    function printStatusCli($title, $value, $ok, $details = '') {
        $symbol = $ok ? "[OK]" : "[FAIL]";
        echo "$symbol $title: $value" . ($details ? " ($details)" : "") . "\n";
    }
    // Redefine to CLI version
    function printStatus($title, $value, $ok, $details = '') {
        printStatusCli($title, $value, $ok, $details);
    }
} else {
    // Output HTML header
    echo "<!DOCTYPE html><html><head><title>User Check: $uid</title>";
    echo "<style>body{background-color:#0d1117;color:#c9d1d9;font-family:sans-serif;padding:20px;}";
    echo "h1,h2{color:#58a6ff;border-bottom:1px solid #21262d;}";
    echo "pre{background-color:#161b22;padding:15px;border:1px solid #30363d;border-radius:5px;overflow:auto;}";
    echo "table{border-collapse:collapse;width:100%;margin-bottom:20px;}";
    echo "th,td{border:1px solid #30363d;padding:8px;text-align:left;}";
    echo "th{background-color:#21262d;}";
    echo ".alert{background-color:rgba(248,81,73,0.1);border:1px solid rgba(248,81,73,0.4);padding:15px;border-radius:5px;color:#ff7b72;margin-bottom:20px;}";
    echo ".success-box{background-color:rgba(56,139,253,0.1);border:1px solid rgba(56,139,253,0.4);padding:15px;border-radius:5px;color:#58a6ff;margin-bottom:20px;}";
    echo "</style></head><body>";
}

echo "<h1>Diagnostic Report for UID: " . htmlspecialchars($uid) . "</h1>";

try {
    $db = getDB();

    // 1. Fetch App Settings
    $stmt = $db->query("SELECT daily_refresh FROM app_settings LIMIT 1");
    $appSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    $dailyRefreshEnabled = $appSettings ? intval($appSettings['daily_refresh']) : 0;
    
    echo "<h2>1. Global Configuration Check</h2>";
    printStatus("Daily Refresh Enabled globally", $dailyRefreshEnabled === 1 ? "Yes (1)" : "No (0)", $dailyRefreshEnabled === 1);

    // 2. Fetch User Campaigns
    $stmt = $db->prepare("SELECT * FROM user_campaigns WHERE rewardbro_uid = ?");
    $stmt->execute([$uid]);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>2. User Campaign Status</h2>";
    if (empty($campaigns)) {
        printStatus("Campaign record found", "No", false, "User has not started or attributed any campaign yet");
    } else {
        foreach ($campaigns as $camp) {
            $status = $camp['status'];
            $deviceId = $camp['device_id'];
            $updatedAt = $camp['updated_at'];
            
            printStatus("Campaign status for device " . $deviceId, $status, $status === 'completed', "Last updated: " . $updatedAt);
            
            // Check completed date comparison if status is completed
            if ($status === 'completed') {
                $completedDate = new DateTime($updatedAt);
                $currentDate = new DateTime();
                $isNewDay = $currentDate->format('Y-m-d') > $completedDate->format('Y-m-d');
                printStatus(
                    "Time Comparison (Current Date > Completed Date)", 
                    $currentDate->format('Y-m-d') . " > " . $completedDate->format('Y-m-d'), 
                    $isNewDay, 
                    $isNewDay ? "Ready to refresh on next app launch" : "Refresh will happen tomorrow"
                );
            }
        }
    }

    // 3. Check Postback Settings and History
    echo "<h2>3. Target Levels and Postback Status</h2>";
    $maxSettingsStmt = $db->query("SELECT MAX(target_level) as max_level FROM postback_settings");
    $maxLevelRow = $maxSettingsStmt->fetch();
    $maxLevel = $maxLevelRow ? intval($maxLevelRow['max_level']) : 0;
    
    echo "Max configured level in settings: <strong>" . $maxLevel . "</strong><br><br>";

    $stmtPb = $db->prepare("SELECT * FROM postback_history WHERE rewardbro_uid = ? ORDER BY level ASC");
    $stmtPb->execute([$uid]);
    $postbacks = $stmtPb->fetchAll(PDO::FETCH_ASSOC);

    $completedMaxLevelPostback = false;
    if (empty($postbacks)) {
        printStatus("Postbacks fired", "None", false, "No postbacks have been sent for this user");
    } else {
        echo "<table><thead><tr><th>Level</th><th>Response Status</th><th>Response Body</th><th>Fired At</th></tr></thead><tbody>";
        foreach ($postbacks as $pb) {
            $isSuccess = intval($pb['response_status']) >= 200 && intval($pb['response_status']) < 300;
            echo "<tr>";
            echo "<td>Level " . htmlspecialchars($pb['level']) . "</td>";
            echo "<td>" . htmlspecialchars($pb['response_status']) . "</td>";
            echo "<td>" . htmlspecialchars($pb['response_body']) . "</td>";
            echo "<td>" . htmlspecialchars($pb['created_at']) . "</td>";
            echo "</tr>";
            
            if ($pb['level'] == $maxLevel && $isSuccess) {
                $completedMaxLevelPostback = true;
            }
        }
        echo "</tbody></table>";
    }

    // 4. Check user levels and bans
    echo "<h2>4. User Level & Security Info</h2>";
    $stmtUser = $db->prepare("SELECT * FROM users WHERE rewardbro_uid = ?");
    $stmtUser->execute([$uid]);
    $users = $stmtUser->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        // Fallback search by device id
        $deviceIds = array_column($campaigns, 'device_id');
        if (!empty($deviceIds)) {
            $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
            $stmtUser = $db->prepare("SELECT * FROM users WHERE device_id IN ($placeholders)");
            $stmtUser->execute($deviceIds);
            $users = $stmtUser->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    if (empty($users)) {
        printStatus("User Profile", "Not found in users table", false);
    } else {
        foreach ($users as $u) {
            $isBanned = intval($u['is_banned']) === 1;
            printStatus("User level for device " . $u['device_id'], $u['current_level'], true);
            printStatus("Banned status", $isBanned ? "Banned" : "Not Banned", !$isBanned, $isBanned ? "Device blocked" : "");
            
            // Query level bypass logs for this device
            $stmtBypass = $db->prepare("SELECT * FROM level_bypass_logs WHERE device_id = ? ORDER BY created_at DESC");
            $stmtBypass->execute([$u['device_id']]);
            $bypass = $stmtBypass->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($bypass)) {
                printStatus("Bypass attempts detected", count($bypass) . " attempt(s)", false, "User tried jumping levels unlawfully");
                echo "<h3>Level Bypass Details:</h3>";
                echo "<table><thead><tr><th>Attempted Level</th><th>DB Level</th><th>IP</th><th>Time</th></tr></thead><tbody>";
                foreach ($bypass as $bp) {
                    echo "<tr><td>" . htmlspecialchars($bp['attempted_level']) . "</td><td>" . htmlspecialchars($bp['current_db_level']) . "</td><td>" . htmlspecialchars($bp['ip_address']) . "</td><td>" . htmlspecialchars($bp['created_at']) . "</td></tr>";
                }
                echo "</tbody></table>";
            }
        }
    }

    // 5. Final Diagnostic Conclusion
    echo "<h2>5. Diagnosis Summary</h2>";
    
    $reasons = [];
    $refreshPossible = true;

    if ($dailyRefreshEnabled !== 1) {
        $reasons[] = "Daily refresh is globally disabled in app_settings (daily_refresh = 0).";
        $refreshPossible = false;
    }

    if (empty($campaigns)) {
        $reasons[] = "No active campaign record found in user_campaigns table for this UID.";
        $refreshPossible = false;
    } else {
        foreach ($campaigns as $camp) {
            if ($camp['status'] !== 'completed') {
                $reasons[] = "Campaign status for device " . $camp['device_id'] . " is currently '" . $camp['status'] . "' (must be 'completed' to refresh).";
                $refreshPossible = false;
                
                // Explain why campaign is not completed
                if (!$completedMaxLevelPostback) {
                    $reasons[] = "Reason for incomplete campaign: The final target level ($maxLevel) postback was not successfully sent/received (HTTP status 200). Check if the user reached level $maxLevel or if their postbacks were cancelled/failed.";
                }
            } else {
                $completedDate = new DateTime($camp['updated_at']);
                $currentDate = new DateTime();
                if ($currentDate->format('Y-m-d') <= $completedDate->format('Y-m-d')) {
                    $reasons[] = "Campaign completed today (completed: " . $camp['updated_at'] . "). Daily refresh will be active from tomorrow onwards.";
                    $refreshPossible = false;
                }
            }
        }
    }

    foreach ($users as $u) {
        if (intval($u['is_banned']) === 1) {
            $reasons[] = "User device " . $u['device_id'] . " is banned. Banned players cannot refresh or proceed.";
            $refreshPossible = false;
        }
    }

    if ($refreshPossible) {
        echo "<div class='success-box'>";
        echo "<strong>Campaign is eligible for daily refresh!</strong><br>";
        echo "When the user opens the app next time, the app will execute the 'get_settings' action, which will automatically reset their current level to 1 and change their campaign status back to 'active' on the server.";
        echo "</div>";
    } else {
        echo "<div class='alert'>";
        echo "<strong>Daily refresh did not trigger because:</strong><ul>";
        foreach ($reasons as $reason) {
            echo "<li>" . htmlspecialchars($reason) . "</li>";
        }
        echo "</ul></div>";
    }

    // 6. Database Dumps
    echo "<h2>6. Raw Database Dumps</h2>";
    echo "<h3>User Campaigns:</h3><pre>" . htmlspecialchars(print_r($campaigns, true)) . "</pre>";
    echo "<h3>Postback History:</h3><pre>" . htmlspecialchars(print_r($postbacks, true)) . "</pre>";
    if (!empty($users)) {
        echo "<h3>User Profiles:</h3><pre>" . htmlspecialchars(print_r($users, true)) . "</pre>";
    }

} catch (Exception $e) {
    echo "<div class='alert'>Database Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

if (!$isCli) {
    echo "</body></html>";
}
