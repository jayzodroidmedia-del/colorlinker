<?php
require_once __DIR__ . '/config.php';

// Authentication Check
$pin = $_GET['pin'] ?? '';
if ($pin !== 'mint3') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied. Pass ?pin=mint3 to access.");
}

$uid = 'sgQo4a3Hu5UGLOWEGp7qWcPrirs1';

echo "<!DOCTYPE html><html><head><title>Full Check: $uid</title>";
echo "<style>body{background-color:#0d1117;color:#c9d1d9;font-family:sans-serif;padding:20px;}";
echo "h1,h2{color:#58a6ff;border-bottom:1px solid #21262d;}";
echo "pre{background-color:#161b22;padding:15px;border:1px solid #30363d;border-radius:5px;overflow:auto;}";
echo ".status-badge{padding:3px 8px;border-radius:12px;font-weight:bold;}";
echo ".active{background-color:rgba(46,160,67,0.15);color:#3fb950;}";
echo ".completed{background-color:rgba(56,139,253,0.15);color:#58a6ff;}";
echo "</style></head><body>";

echo "<h1>Full Reward Refresh Diagnostic for UID: $uid</h1>";

try {
    $db = getDB();

    // 1. Timezone info
    echo "<h2>1. Server & Database Timezone</h2>";
    echo "PHP Default Timezone: <strong>" . date_default_timezone_get() . "</strong><br>";
    echo "PHP Current Time: <strong>" . date('Y-m-d H:i:s') . "</strong><br>";
    
    $dbTimeStmt = $db->query("SELECT NOW() as db_time, @@global.time_zone as gtz, @@session.time_zone as stz");
    $dbTime = $dbTimeStmt->fetch(PDO::FETCH_ASSOC);
    echo "Database Current Time: <strong>" . $dbTime['db_time'] . "</strong><br>";
    echo "Database Timezone (Global/Session): <strong>" . $dbTime['gtz'] . " / " . $dbTime['stz'] . "</strong><br>";

    // 2. Fetch campaign
    echo "<h2>2. Campaign Status</h2>";
    $campStmt = $db->prepare("SELECT * FROM user_campaigns WHERE rewardbro_uid = ?");
    $campStmt->execute([$uid]);
    $campaign = $campStmt->fetch(PDO::FETCH_ASSOC);

    if (!$campaign) {
        echo "<span style='color:red;'>No campaign found in user_campaigns for this UID!</span><br>";
        $deviceId = null;
    } else {
        $deviceId = $campaign['device_id'];
        $statusClass = ($campaign['status'] === 'completed') ? 'completed' : 'active';
        echo "Device ID: <code>" . htmlspecialchars($campaign['device_id']) . "</code><br>";
        echo "Campaign Status: <span class='status-badge $statusClass'>" . htmlspecialchars($campaign['status']) . "</span><br>";
        echo "Campaign Created At: <strong>" . $campaign['created_at'] . "</strong><br>";
        echo "Campaign Updated At: <strong>" . $campaign['updated_at'] . "</strong><br>";
    }

    // 3. Fetch User Level
    echo "<h2>3. User Profile Status</h2>";
    if ($deviceId) {
        $userStmt = $db->prepare("SELECT * FROM users WHERE device_id = ?");
        $userStmt->execute([$deviceId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            echo "Current Level in `users` table: <strong>" . $user['current_level'] . "</strong><br>";
            echo "Is Banned: <strong>" . ($user['is_banned'] ? 'Yes (Banned)' : 'No') . "</strong><br>";
            echo "Last Update At: <strong>" . $user['updated_at'] . "</strong><br>";
        } else {
            echo "<span style='color:red;'>No user profile found for device ID $deviceId!</span><br>";
        }
    } else {
        echo "Cannot check user table because device ID was not found from the campaign.<br>";
    }

    // 4. Simulate Daily Refresh Check
    echo "<h2>4. Daily Refresh Simulation Check</h2>";
    
    $stmtSettings = $db->query("SELECT daily_refresh FROM app_settings LIMIT 1");
    $settings = $stmtSettings->fetch(PDO::FETCH_ASSOC);
    $dailyRefresh = $settings ? intval($settings['daily_refresh']) : 0;
    
    echo "Global Daily Refresh Switch: <strong>" . ($dailyRefresh ? 'ON (1)' : 'OFF (0)') . "</strong><br><br>";

    if ($dailyRefresh !== 1) {
        echo "❌ Refresh will not trigger because daily_refresh is disabled globally.<br>";
    } else if (!$campaign) {
        echo "❌ Refresh will not trigger because campaign record is missing.<br>";
    } else {
        echo "Evaluating campaign status...<br>";
        if ($campaign['status'] === 'completed') {
            echo "Campaign is currently <strong>completed</strong>.<br>";
            $completedDate = new DateTime($campaign['updated_at']);
            $currentDate = new DateTime();
            
            echo "Completion Date: <strong>" . $completedDate->format('Y-m-d') . "</strong><br>";
            echo "Current Date: <strong>" . $currentDate->format('Y-m-d') . "</strong><br>";
            
            if ($currentDate->format('Y-m-d') > $completedDate->format('Y-m-d')) {
                echo "✅ Date check passed: Current date is after completion date. <strong>Daily refresh is ready to trigger on next app startup!</strong><br>";
            } else {
                echo "❌ Date check failed: Current date is equal to completion date. <strong>The daily refresh will trigger tomorrow.</strong><br>";
            }
        } else {
            echo "Campaign is currently <strong>active</strong>.<br>";
            echo "If the user has completed all levels (e.g. level 45), let's check why status is active:<br>";
            
            // Check if level 45 postback exists
            $pbStmt = $db->prepare("SELECT * FROM postback_history WHERE rewardbro_uid = ? AND level = 45");
            $pbStmt->execute([$uid]);
            $pb45 = $pbStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($pb45) {
                echo "• Level 45 postback exists in postback_history! Fired at: <strong>" . $pb45['created_at'] . "</strong><br>";
                echo "• Postback response status was: <strong>" . $pb45['response_status'] . "</strong><br>";
                
                // If postback succeeded, why is campaign active?
                if (intval($pb45['response_status']) >= 200 && intval($pb45['response_status']) < 300) {
                    echo "• <span style='color:orange;'>Warning:</span> The postback succeeded but campaign is still 'active'. This suggests:<br>";
                    echo "  1) The campaign <strong>was already completed and was reset back to 'active' today</strong> by the daily refresh on app startup! (Check if their user level was reset to 1).<br>";
                    echo "  2) Or the campaign table was manually modified.<br>";
                } else {
                    echo "• The postback did not succeed (status was not 200-299), so campaign status was not updated to completed.<br>";
                }
            } else {
                echo "• No postback for level 45 exists in postback_history. The player has not completed level 45 on the server yet, or the postback was blocked by anti-cheat.<br>";
            }
        }
    }

} catch (Exception $e) {
    echo "<span style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "</body></html>";
