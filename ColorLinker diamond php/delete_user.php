<?php
require_once __DIR__ . '/config.php';

// 1. Security Pin Authentication
$pin = $_GET['pin'] ?? '';
if ($pin !== 'mint3') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied. Pass the correct ?pin= to access.");
}

// 2. Fetch device_id parameter
$deviceId = trim($_GET['device_id'] ?? '');

echo "<!DOCTYPE html><html><head><title>Delete User Data Manager</title>";
echo "<style>body{background-color:#0d1117;color:#c9d1d9;font-family:sans-serif;padding:20px;}";
echo "h1,h2{color:#ff7b72;border-bottom:1px solid #21262d;padding-bottom:8px;}";
echo "pre{background-color:#161b22;padding:15px;border:1px solid #30363d;border-radius:5px;overflow:auto;}";
echo ".info-box{background-color:rgba(56,139,253,0.1);border:1px solid rgba(56,139,253,0.4);padding:15px;border-radius:5px;color:#58a6ff;margin-bottom:20px;}";
echo ".success-box{background-color:rgba(46,160,67,0.1);border:1px solid rgba(46,160,67,0.4);padding:15px;border-radius:5px;color:#3fb950;margin-bottom:20px;}";
echo ".form-box{background-color:#161b22;border:1px solid #30363d;padding:20px;border-radius:6px;max-width:600px;margin:30px auto;}";
echo "input[type='text']{width:100%;padding:10px;background-color:#0d1117;border:1px solid #30363d;border-radius:4px;color:#c9d1d9;box-sizing:border-box;margin-bottom:15px;}";
echo "button{padding:10px 20px;background-color:#da3637;border:1px solid #30363d;border-radius:4px;color:#f0f6fc;font-weight:bold;cursor:pointer;width:100%;font-size:16px;}";
echo "button:hover{background-color:#b82b2b;}";
echo "</style></head><body>";

if (empty($deviceId)) {
    // Render input form if device_id is not provided
    echo "<div class='form-box'>";
    echo "<h2>Delete Device Database History</h2>";
    echo "<p style='color:#8b949e;font-size:14px;margin-bottom:20px;'>Enter the Device ID (ANDROID_ID) of the player to completely wipe all database records linked to it.</p>";
    echo "<form method='GET'>";
    echo "<input type='hidden' name='pin' value='" . htmlspecialchars($pin) . "'>";
    echo "<label for='device_id' style='display:block;font-weight:bold;margin-bottom:8px;color:#c9d1d9;'>Device ID:</label>";
    echo "<input type='text' id='device_id' name='device_id' placeholder='e.g. c550b7bde7666cd2' required autocomplete='off'>";
    echo "<button type='submit' onclick='return confirm(\"Are you sure you want to delete all database records for this device?\");'>Wipe Device Data</button>";
    echo "</form>";
    echo "</div>";
    echo "</body></html>";
    exit;
}

echo "<h1>Deleting Data for Device ID: " . htmlspecialchars($deviceId) . "</h1>";

try {
    $db = getDB();

    // 1. Retrieve rewardbro_uid and gaid before deleting, so we can clean up campaign clicks too
    $rewardbroUid = null;
    $gaid = null;

    $stmt = $db->prepare("SELECT rewardbro_uid, gid FROM device_keys WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $dkRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($dkRow) {
        $rewardbroUid = $dkRow['rewardbro_uid'];
        $gaid = $dkRow['gid'];
    }

    if (!$rewardbroUid) {
        $stmt = $db->prepare("SELECT rewardbro_uid FROM users WHERE device_id = ?");
        $stmt->execute([$deviceId]);
        $uRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($uRow) {
            $rewardbroUid = $uRow['rewardbro_uid'];
        }
    }

    echo "<div class='info-box'>";
    echo "Linked RewardBro UID found: <strong>" . ($rewardbroUid ?: 'None') . "</strong><br>";
    echo "Linked GAID (Google Ad ID) found: <strong>" . ($gaid ?: 'None') . "</strong><br>";
    echo "</div>";

    // 2. Perform deletions from all tables
    $deletions = [];

    // Table: user_campaigns (usually cascades, but we delete explicitly for safety)
    $stmt = $db->prepare("DELETE FROM user_campaigns WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $deletions['user_campaigns'] = $stmt->rowCount();

    // Table: rewards (usually cascades)
    $stmt = $db->prepare("DELETE FROM rewards WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $deletions['rewards'] = $stmt->rowCount();

    // Table: users
    $stmt = $db->prepare("DELETE FROM users WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $deletions['users'] = $stmt->rowCount();

    // Table: device_keys
    $stmt = $db->prepare("DELETE FROM device_keys WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $deletions['device_keys'] = $stmt->rowCount();

    // Table: level_history
    $stmt = $db->prepare("DELETE FROM level_history WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $deletions['level_history'] = $stmt->rowCount();

    // Table: level_bypass_logs
    $stmt = $db->prepare("DELETE FROM level_bypass_logs WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $deletions['level_bypass_logs'] = $stmt->rowCount();

    // Table: postback_history
    $stmt = $db->prepare("DELETE FROM postback_history WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $deletions['postback_history'] = $stmt->rowCount();

    // Table: security_logs
    $stmt = $db->prepare("DELETE FROM security_logs WHERE device_id = ?");
    $stmt->execute([$deviceId]);
    $deletions['security_logs'] = $stmt->rowCount();

    // Table: campaign_clicks (delete by UID and GAID if found)
    $deletions['campaign_clicks'] = 0;
    if (!empty($rewardbroUid) || !empty($gaid)) {
        $sql = "DELETE FROM campaign_clicks WHERE 1=0";
        $params = [];
        if (!empty($rewardbroUid)) {
            $sql .= " OR rewardbro_uid = ?";
            $params[] = $rewardbroUid;
        }
        if (!empty($gaid)) {
            $sql .= " OR gaid = ?";
            $params[] = $gaid;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $deletions['campaign_clicks'] = $stmt->rowCount();
    }

    echo "<div class='success-box'>";
    echo "<h3>Deletion Summary:</h3><ul>";
    foreach ($deletions as $table => $count) {
        echo "<li>Table <strong>$table</strong>: deleted <strong>$count</strong> row(s)</li>";
    }
    echo "</ul></div>";
    echo "<p>Device data has been completely wiped. The user can now start registration and campaigns as a brand new device.</p>";

} catch (Exception $e) {
    echo "<span style='color:red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "</body></html>";
