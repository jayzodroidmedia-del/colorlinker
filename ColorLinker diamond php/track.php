<?php
require_once __DIR__ . '/config.php';

// 1. Capture tracking parameters from GET request
$offerId      = $_GET['offerId'] ?? '';
$rewardbroUid = $_GET['userId'] ?? '';
$eventId      = $_GET['eventId'] ?? '';
$gaid         = $_GET['gaid'] ?? $_GET['gid'] ?? '';

// 2. Validate essential parameters
if (empty($offerId) || empty($rewardbroUid) || empty($eventId)) {
    // If tracking params are missing, fall back to direct Play Store redirect silently
    header("Location: https://play.google.com/store/apps/details?id=com.colorlinker.puzzle");
    exit;
}

// 3. Collect client fingerprint details
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    $db = getDB();
    
    // Check if the user (by rewardbroUid) or the device (by gaid) is already attributed
    $alreadyAttributed = false;
    
    if (!empty($rewardbroUid)) {
        $stmtCheckUid = $db->prepare("SELECT device_id FROM user_campaigns WHERE rewardbro_uid = ? LIMIT 1");
        $stmtCheckUid->execute([$rewardbroUid]);
        if ($stmtCheckUid->fetch()) {
            $alreadyAttributed = true;
        }
    }
    
    if (!$alreadyAttributed && !empty($gaid)) {
        $stmtCheckGaid = $db->prepare("SELECT dk.device_id FROM device_keys dk 
                                       JOIN user_campaigns uc ON dk.device_id = uc.device_id 
                                       WHERE dk.gid = ? LIMIT 1");
        $stmtCheckGaid->execute([$gaid]);
        if ($stmtCheckGaid->fetch()) {
            $alreadyAttributed = true;
        }
    }

    if (!$alreadyAttributed) {
        $referrer = generateRandomKey(8); // Generates a secure random 8-char string (e.g. Bjd762gn)

        // 4. Log the click with pending status for GAID/Referrer attribution on install
        $stmt = $db->prepare("INSERT INTO campaign_clicks (offer_id, rewardbro_uid, event_id, ip_address, user_agent, referrer, status, gaid) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([$offerId, $rewardbroUid, $eventId, $ip, $ua, $referrer, $gaid]);
    } else {
        $referrer = null; // Skip logging click and skip custom referrer since they are already attributed
    }
} catch (Exception $e) {
    // Log exception but do not block redirect
    $referrer = null;
    logSecurity('unknown', 'click_log_failed', $e->getMessage());
}

// 5. Redirect user to Google Play Store to install the app
if ($referrer) {
    header("Location: https://play.google.com/store/apps/details?id=com.colorlinker.puzzle&referrer=referr%3D" . $referrer);
} else {
    header("Location: https://play.google.com/store/apps/details?id=com.colorlinker.puzzle");
}
exit;
?>
