<?php
require_once __DIR__ . '/config.php';

$uid = 'sgQo4a3Hu5UGLOWEGp7qWcPrirs1';

echo "=== CHECKING REWARD STATUS FOR UID: $uid ===\n\n";

try {
    $db = getDB();

    // 1. Check App Settings
    $stmt = $db->query("SELECT * FROM app_settings LIMIT 1");
    $appSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "--- APP SETTINGS ---\n";
    print_r($appSettings);
    echo "\n";

    // 2. Query campaigns for this user
    $stmt = $db->prepare("SELECT * FROM user_campaigns WHERE rewardbro_uid = ?");
    $stmt->execute([$uid]);
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "--- USER CAMPAIGNS ---\n";
    print_r($campaigns);
    echo "\n";

    // Collect all related device IDs
    $deviceIds = [];
    foreach ($campaigns as $camp) {
        if (!empty($camp['device_id'])) {
            $deviceIds[$camp['device_id']] = true;
        }
    }

    // 3. Query users table
    $stmt = $db->prepare("SELECT * FROM users WHERE rewardbro_uid = ?");
    $stmt->execute([$uid]);
    $usersByUid = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($usersByUid as $u) {
        if (!empty($u['device_id'])) {
            $deviceIds[$u['device_id']] = true;
        }
    }

    // Also look up users by device IDs
    $users = [];
    if (!empty($deviceIds)) {
        $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
        $stmt = $db->prepare("SELECT * FROM users WHERE device_id IN ($placeholders)");
        $stmt->execute(array_keys($deviceIds));
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo "--- USERS TABLE RECORDS ---\n";
    print_r($usersByUid);
    if ($users != $usersByUid) {
        echo "Users found by device ID:\n";
        print_r($users);
    }
    echo "\n";

    // 4. Query device keys
    $deviceKeys = [];
    if (!empty($deviceIds)) {
        $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
        $stmt = $db->prepare("SELECT id, device_id, is_active, is_registered, key_delivered, created_at, updated_at, last_request_at, rewardbro_uid, referrer, gid FROM device_keys WHERE device_id IN ($placeholders)");
        $stmt->execute(array_keys($deviceIds));
        $deviceKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo "--- DEVICE KEYS ---\n";
    print_r($deviceKeys);
    echo "\n";

    // 5. Query clicks
    $stmt = $db->prepare("SELECT * FROM campaign_clicks WHERE rewardbro_uid = ?");
    $stmt->execute([$uid]);
    $clicks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "--- CAMPAIGN CLICKS ---\n";
    print_r($clicks);
    echo "\n";

    // 6. Query postback history
    $stmt = $db->prepare("SELECT * FROM postback_history WHERE rewardbro_uid = ? ORDER BY created_at DESC");
    $stmt->execute([$uid]);
    $postbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "--- POSTBACK HISTORY ---\n";
    print_r($postbacks);
    echo "\n";

    // 7. Query level bypass logs
    $bypassLogs = [];
    if (!empty($deviceIds)) {
        $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
        $stmt = $db->prepare("SELECT * FROM level_bypass_logs WHERE device_id IN ($placeholders) ORDER BY created_at DESC");
        $stmt->execute(array_keys($deviceIds));
        $bypassLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo "--- LEVEL BYPASS LOGS ---\n";
    print_r($bypassLogs);
    echo "\n";

    // 8. Query level history
    $levelHistory = [];
    if (!empty($deviceIds)) {
        $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
        $stmt = $db->prepare("SELECT level, created_at, ip_address FROM level_history WHERE device_id IN ($placeholders) ORDER BY created_at DESC LIMIT 50");
        $stmt->execute(array_keys($deviceIds));
        $levelHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo "--- LEVEL HISTORY (Last 50) ---\n";
    print_r($levelHistory);
    echo "\n";

    // 9. Query security logs
    $secLogs = [];
    if (!empty($deviceIds)) {
        $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
        $stmt = $db->prepare("SELECT * FROM security_logs WHERE device_id IN ($placeholders) ORDER BY created_at DESC LIMIT 50");
        $stmt->execute(array_keys($deviceIds));
        $secLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    echo "--- SECURITY LOGS (Last 50) ---\n";
    print_r($secLogs);
    echo "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
