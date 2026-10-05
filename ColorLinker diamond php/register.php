<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/decrypt_helpers.php';
require_once __DIR__ . '/device_check.php';

$data = $_POST['spoint'] ?? '';
if (empty($data)) respond(false, 'No data received');

try {
    $fields = decryptPayload($data, HARDCODED_MAIN_KEY, HARDCODED_MASTER_SECRET, ['rsa2_public_key','device_info']);
} catch (Exception $e) { respond(false, 'Decrypt failed: ' . $e->getMessage()); }

$deviceId   = $fields['device_id'] ?? '';
$action     = $fields['action'] ?? '';
$rsa2PubKey = $fields['rsa2_public_key'] ?? '';
$deviceInfo = $fields['device_info'] ?? '';
$pkgName    = $fields['pkg'] ?? '';
$timestamp  = intval($fields['milisecond'] ?? 0);
$referrer   = $fields['referrer'] ?? '';
$gid        = $fields['gid'] ?? '';

// Log incoming request details for debugging
logSecurity($deviceId, 'register_request_received', "Referrer from client: " . ($referrer ?: 'empty') . " | GID: " . ($gid ?: 'empty'));

if (empty($deviceId) || $action !== 'register' || empty($rsa2PubKey)) respond(false, 'Invalid request');
if (abs(round(microtime(true) * 1000) - $timestamp) > REQUEST_EXPIRY_MS) respond(false, 'Request expired');

// Device Integrity Verification
if (!empty($deviceInfo) && $deviceInfo !== '{}') {
    $reason = '';
    if (!isRealDevice($deviceInfo, GOOGLE_PACKAGE_NAME, $deviceId, $reason)) {
        logSecurity($deviceId, 'registration_device_failed', $reason);
        respond(false, "device_failed: $reason");
    }
}

$db = getDB(); $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Check if user is banned
try {
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'is_banned'");
    if (!$stmt->fetch()) {
        $db->exec("ALTER TABLE users ADD COLUMN is_banned TINYINT(1) DEFAULT 0 AFTER current_level");
    }
} catch (Exception $e) {}

$banStmt = $db->prepare("SELECT is_banned FROM users WHERE device_id = ? LIMIT 1");
$banStmt->execute([$deviceId]);
$banRow = $banStmt->fetch(PDO::FETCH_ASSOC);
if ($banRow && intval($banRow['is_banned']) === 1) {
    $db->prepare("UPDATE device_keys SET is_active = 0 WHERE device_id = ?")->execute([$deviceId]);
    respond(false, 'Device banned');
}

// Generate new RSA1 Keypair for this device session
$rsa1Res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
if (!$rsa1Res) respond(false, 'Key generation failed');
openssl_pkey_export($rsa1Res, $rsa1PrivKey);
$rsa1PubKey = openssl_pkey_get_details($rsa1Res)['key'];

$deviceMainKey = generateRandomKey(32);
$deviceMasterSecret = generateRandomKey(48);

$stmt = $db->prepare("SELECT id FROM device_keys WHERE device_id = ?");
$stmt->execute([$deviceId]);
$existing = $stmt->fetch();

if ($existing) {
    $db->prepare("UPDATE device_keys SET rsa1_public_key=?,rsa1_private_key=?,rsa2_public_key=?,rsa2_private_key=NULL,
        device_main_key=?,device_master_secret=?,is_registered=1,key_delivered=0,pkg_name=?,registration_ip=?,gid=?,updated_at=NOW() WHERE device_id=?")
    ->execute([$rsa1PubKey,$rsa1PrivKey,$rsa2PubKey,$deviceMainKey,$deviceMasterSecret,$pkgName,$ip,$gid,$deviceId]);
    $status = 'logged_in';
    logSecurity($deviceId, 'login_success', 'Reinstall — keys updated');
} else {
    $db->prepare("INSERT INTO device_keys (device_id,rsa1_public_key,rsa1_private_key,rsa2_public_key,
        device_main_key,device_master_secret,is_active,is_registered,key_delivered,pkg_name,registration_ip,gid) VALUES (?,?,?,?,?,?,1,1,0,?,?,?)")
    ->execute([$deviceId,$rsa1PubKey,$rsa1PrivKey,$rsa2PubKey,$deviceMainKey,$deviceMasterSecret,$pkgName,$ip,$gid]);
    $status = 'registered';
    logSecurity($deviceId, 'registration_success', 'New device');
}

// Automatically register the user device in main users table and save GID
try {
    $db->prepare("INSERT INTO users (device_id, gid) VALUES (?, ?) ON DUPLICATE KEY UPDATE gid = VALUES(gid)")->execute([$deviceId, $gid]);
    
    // Log Level 1 in history for new/reinstalled devices to start validation from Level 1
    $histCheck = $db->prepare("SELECT id FROM level_history WHERE device_id = ? AND level = 1 LIMIT 1");
    $histCheck->execute([$deviceId]);
    if (!$histCheck->fetch()) {
        $db->prepare("INSERT INTO level_history (device_id, level, ip_address) VALUES (?, 1, ?)")
           ->execute([$deviceId, $ip]);
    }
} catch (Exception $e) {}

// Campaign Attribution Logic
try {
    // Check if this device is already attributed to a campaign
    $campCheck = $db->prepare("SELECT device_id FROM user_campaigns WHERE device_id = ? LIMIT 1");
    $campCheck->execute([$deviceId]);
    $alreadyAttributed = $campCheck->fetch();

    if (!$alreadyAttributed) {
        $pendingClick = null;
        $isReferrerAttribution = false;
        $isGaidAttribution = false;
        
        // 1. Try direct referrer matching first (Naya version / Referrer)
        if (!empty($referrer)) {
            $clickStmt = $db->prepare("SELECT id, offer_id, rewardbro_uid, event_id, referrer FROM campaign_clicks 
                                       WHERE referrer = ? AND status = 'pending' LIMIT 1");
            $clickStmt->execute([$referrer]);
            $pendingClick = $clickStmt->fetch(PDO::FETCH_ASSOC);
            if ($pendingClick) {
                $isReferrerAttribution = true;
            }
        }
        
        // 2. Try GAID (Google Advertising ID) matching if no referrer or referrer not found
        if (!$pendingClick && !empty($gid)) {
            $clickStmt = $db->prepare("SELECT id, offer_id, rewardbro_uid, event_id, referrer FROM campaign_clicks 
                                       WHERE gaid = ? AND status = 'pending' ORDER BY created_at DESC LIMIT 1");
            $clickStmt->execute([$gid]);
            $pendingClick = $clickStmt->fetch(PDO::FETCH_ASSOC);
            if ($pendingClick) {
                $isGaidAttribution = true;
            }
        }

        if ($pendingClick) {
            $matchedClickId = $pendingClick['id'];
            $matchedReferrer = $pendingClick['referrer'];
            $offerId = $pendingClick['offer_id'];
            $rewardbroUid = $pendingClick['rewardbro_uid'];
            $eventId = $pendingClick['event_id'];
            
            // Set referrer in user_campaigns/device_keys if it was a Referrer or GAID install
            $saveReferrer = ($isReferrerAttribution || $isGaidAttribution) ? $matchedReferrer : null;

            // Link device to the campaign
            $campStmt = $db->prepare("INSERT INTO user_campaigns (device_id, rewardbro_uid, offer_id, event_id, referrer, status, created_at) 
                                     VALUES (?, ?, ?, ?, ?, 'active', NOW()) 
                                     ON DUPLICATE KEY UPDATE rewardbro_uid = VALUES(rewardbro_uid), event_id = VALUES(event_id), referrer = COALESCE(referrer, VALUES(referrer)), status = 'active', created_at = NOW()");
            $campStmt->execute([$deviceId, $rewardbroUid, $offerId, $eventId, $saveReferrer]);

            // Reset user level and level history on re-attribution/attribution (if row existed somehow)
            $db->prepare("UPDATE users SET current_level = 1 WHERE device_id = ?")->execute([$deviceId]);
            $db->prepare("DELETE FROM level_history WHERE device_id = ? AND level > 1")->execute([$deviceId]);

            // Update click status to attributed
            $updateClickStmt = $db->prepare("UPDATE campaign_clicks SET status = 'attributed' WHERE id = ?");
            $updateClickStmt->execute([$matchedClickId]);

            // Save rewardbro_uid and referrer in device_keys table
            $db->prepare("UPDATE device_keys SET rewardbro_uid = ?, referrer = COALESCE(referrer, ?) WHERE device_id = ?")->execute([$rewardbroUid, $saveReferrer, $deviceId]);

            // Save rewardbro_uid in users table
            $db->prepare("UPDATE users SET rewardbro_uid = ? WHERE device_id = ?")->execute([$rewardbroUid, $deviceId]);

            logSecurity($deviceId, 'campaign_attribution_success', "Attributed to offer: $offerId, user: $rewardbroUid, referrer: " . ($saveReferrer ?? 'NULL') . " | Match Method: " . ($isReferrerAttribution ? 'Referrer Code' : 'GAID'));
        }
    } else {
        logSecurity($deviceId, 'campaign_attribution_skipped', 'Device already linked to a campaign. Skipping re-attribution.');
    }
} catch (Exception $e) {
    logSecurity($deviceId, 'campaign_attribution_failed', $e->getMessage());
}

$stmt = $db->prepare("SELECT id FROM device_keys WHERE device_id = ?");
$stmt->execute([$deviceId]);
$keyId = strval($stmt->fetch()['id']);

$responseData = implode('|', [base64_encode($rsa1PubKey), $deviceMainKey, $deviceMasterSecret, $keyId]);

if ($rsa2PubKey === 'EDITOR_RSA2_PUBLIC_KEY') {
    $db->prepare("UPDATE device_keys SET key_delivered=1,rsa2_private_key=NULL WHERE device_id=?")->execute([$deviceId]);
    echo json_encode(['success'=>true,'status'=>$status,'encrypted_data'=>$responseData,'key_id'=>$keyId]);
    exit;
}

// Encrypt the response using the client's RSA2 Public Key
$rsa2Pub = openssl_pkey_get_public($rsa2PubKey);
if (!$rsa2Pub) {
    $rsa2Pub = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n".chunk_split($rsa2PubKey,64,"\n")."-----END PUBLIC KEY-----");
}
if (!$rsa2Pub) respond(false, 'Invalid RSA2 key');

$encChunks = [];
foreach (str_split($responseData, 200) as $chunk) {
    $enc = '';
    if (!openssl_public_encrypt($chunk, $enc, $rsa2Pub)) respond(false, 'Encrypt failed');
    $encChunks[] = base64_encode($enc);
}

$db->prepare("UPDATE device_keys SET key_delivered=1,rsa2_private_key=NULL WHERE device_id=?")->execute([$deviceId]);
echo json_encode(['success'=>true,'status'=>$status,'encrypted_data'=>implode('.',$encChunks),'key_id'=>$keyId]);
?>
