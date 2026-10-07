<?php
header('Content-Type: application/json');
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/decrypt_helpers.php';
require_once __DIR__ . '/device_check.php';



$data = $_POST['spoint'] ?? '';
if (empty($data)) respond(false, 'No data received');

// STEP 1: Identify Device
$deviceId = $_POST['did'] ?? '';
if (empty($deviceId)) {
    try {
        $tmp = json_decode(aesDecrypt($data, HARDCODED_MAIN_KEY), true);
        if ($tmp && isset($tmp['device_id'])) {
            $deviceId = decryptMyString(aesDecrypt($tmp['device_id'],
                deriveFieldKey('device_id', HARDCODED_MASTER_SECRET)));
        }
    } catch (Exception $e) {}
}
if (empty($deviceId)) respond(false, 'No device ID');

// Early ban check in users table
try {
    $dbCheck = getDB();
    $banStmt = $dbCheck->prepare("SELECT is_banned FROM users WHERE device_id = ? LIMIT 1");
    $banStmt->execute([$deviceId]);
    $banRow = $banStmt->fetch(PDO::FETCH_ASSOC);
    if ($banRow && intval($banRow['is_banned']) === 1) {
        respond(false, 'Device banned');
    }
} catch (Exception $e) {}

// STEP 2: Fetch Keys for this Device
$deviceKeys = getDeviceKeys($deviceId);
$result = null;
$aesOnly = ['device_info', 'rsa2_public_key', 'email', 'account', 'account_details', 'feedback_text', 'review_text', 'feedback', 'name', 'mobile', 'method', 'detail'];

// STEP 3: Decrypt Request Payload
if ($deviceKeys) {
    try {
        $result = decryptPayload($data, $deviceKeys['device_main_key'], $deviceKeys['device_master_secret'], $aesOnly);
    } catch (Exception $e) {
        $result = null;
        logSecurity($deviceId, 'decrypt_failed', 'Dynamic keys decryption failed: ' . $e->getMessage());
    }
} else {
    logSecurity($deviceId, 'unregistered_device_attempt', 'No keys found in device_keys table');
}
if (!$result) respond(false, 'Decrypt failed');

// STEP 4: RSA Decrypt for High Security Fields (if enabled/needed)
$rsa = ($result['rsa_enabled'] ?? '0') === '1';

if ($rsa && $deviceKeys) {
    $pk = openssl_pkey_get_private($deviceKeys['rsa1_private_key']);
    if ($pk) {
        foreach (['device_id', 'key_id', 'milisecond'] as $f) {
            if (!empty($result[$f])) {
                $dec = '';
                $b = base64_decode($result[$f]);
                if ($b && openssl_private_decrypt($b, $dec, $pk)) {
                    $result[$f] = $dec;
                } else {
                    logSecurity($deviceId, 'rsa_decrypt_failed', "$f: error=" . openssl_error_string());
                }
            }
        }
    }
}

// STEP 5: Ban check
if ($deviceKeys && !$deviceKeys['is_active']) {
    logSecurity($deviceId, 'device_banned', '');
    respond(false, 'Device banned');
}

// STEP 6: Device Integrity Check
$di = $result['device_info'] ?? '';
if (!empty($di) && $di !== '{}') {
    $reason = '';
    if (!isRealDevice($di, GOOGLE_PACKAGE_NAME, $result['device_id'] ?? $deviceId, $reason)) {
        logSecurity($deviceId, 'device_check_failed', $reason);
        respond(false, "device_failed: $reason");
    }
}

// STEP 7: Check Request Expiry
if (isset($result['milisecond'])) {
    $age = abs(round(microtime(true) * 1000) - intval($result['milisecond']));
    if ($age > REQUEST_EXPIRY_MS) {
        logSecurity($deviceId, 'timestamp_expired', "Age: {$age}ms");
        respond(false, 'Request expired');
    }
}

// Update last request timestamp
if ($deviceKeys) {
    getDB()->prepare("UPDATE device_keys SET last_request_at=NOW() WHERE device_id=?")->execute([$deviceId]);
}

// STEP 8: Route and Handle Actions
$action = $result['action'] ?? '';
if (empty($action)) respond(false, 'Action not specified');

$db = getDB();

switch ($action) {
    case 'get_settings':
        // Fetch App Settings
        $stmt = $db->query("SELECT * FROM app_settings LIMIT 1");
        $adSettings = $stmt->fetch(PDO::FETCH_ASSOC);

        // Fetch User's Current Level from DB
        $userLevel = 1;
        if (!empty($deviceId)) {
            $userStmt = $db->prepare("SELECT current_level FROM users WHERE device_id = ? LIMIT 1");
            $userStmt->execute([$deviceId]);
            $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
            if ($userRow) {
                $userLevel = intval($userRow['current_level']);
            }
        }
        
        // Daily Reset Logic
        $dailyReset = 0;
        $dailyRefreshEnabled = isset($adSettings['daily_refresh']) ? intval($adSettings['daily_refresh']) : 0;

        if ($dailyRefreshEnabled === 1) {
            // Check if this device is linked to a campaign and is completed
            $campResetStmt = $db->prepare("SELECT rewardbro_uid, offer_id, status, updated_at FROM user_campaigns WHERE device_id = ?");
            $campResetStmt->execute([$deviceId]);
            $campaignReset = $campResetStmt->fetch(PDO::FETCH_ASSOC);

            if ($campaignReset && $campaignReset['status'] === 'completed') {
                $clientTimezone = getSecureClientTimezone($result['device_info'] ?? '');
                
                $completedDate = new DateTime($campaignReset['updated_at'], new DateTimeZone('UTC'));
                $completedDate->setTimezone(new DateTimeZone($clientTimezone));
                
                $currentDate = new DateTime('now', new DateTimeZone($clientTimezone));
                
                if ($currentDate->format('Y-m-d') > $completedDate->format('Y-m-d')) {
                    // Perform daily progress reset on server
                    $resetCampStmt = $db->prepare("UPDATE user_campaigns SET status = 'active', created_at = NOW(), updated_at = NOW() WHERE device_id = ?");
                    $resetCampStmt->execute([$deviceId]);
                    
                    $resetUserStmt = $db->prepare("UPDATE users SET current_level = 1 WHERE device_id = ?");
                    $resetUserStmt->execute([$deviceId]);

                    // Reset level history for level 1 under this new daily session
                    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                    $db->prepare("INSERT INTO level_history (device_id, level, ip_address) VALUES (?, 1, ?)")
                       ->execute([$deviceId, $ip]);
                    
                    $dailyReset = 1;
                    
                    logSecurity($deviceId, 'daily_refresh_success', "Successfully reset campaign and level back to 1 for user: " . $campaignReset['rewardbro_uid']);
                }
            }
        }

        // Fetch Ad Controls (11 standard triggers/touchpoints)
        $adControlsStmt = $db->query("SELECT screen_key, ad_type FROM ad_controls");
        $adControl = $adControlsStmt ? $adControlsStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        if (empty($adControl)) {
            $stmtLegacy = $db->query("SELECT screen_name as screen_key, ad_type FROM ad_control");
            $adControl = $stmtLegacy ? $stmtLegacy->fetchAll(PDO::FETCH_ASSOC) : [];
        }
        
        // Fetch Reward Settings
        $stmt = $db->query("SELECT required_level, reward_amount, message, status FROM reward_settings WHERE status = 1");
        $rewardSettings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Install Tasks
        $stmt = $db->query("SELECT level, required_mb FROM install_tasks");
        $installTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Active Banners
        $bannerStmt = $db->query("SELECT id, title, COALESCE(description, '') as description, image_url, action_type, action_value, COALESCE(button_text, '') as button_text, sort_order FROM app_banners WHERE status = 1 ORDER BY sort_order ASC, id ASC");
        $banners = $bannerStmt ? $bannerStmt->fetchAll(PDO::FETCH_ASSOC) : [];

        // Fetch Tester Settings & Day Configs
        $testerSettingsStmt = $db->query("SELECT * FROM tester_settings WHERE id = 1 LIMIT 1");
        $testerSettings = $testerSettingsStmt ? $testerSettingsStmt->fetch(PDO::FETCH_ASSOC) : null;
        if (!$testerSettings) {
            $testerSettings = [
                'instant_approval' => 1,
                'total_days' => 7,
                'reward_amount' => 150,
                'is_active' => 1,
                'badge_text' => 'EARN ₹150 GUARANTEED',
                'badge_color' => '#06D6A0',
                'title' => 'Join the 7-Day Testing Team',
                'description' => 'Play 10 puzzle levels every day for 7 days, submit short daily bug reviews, and claim direct UPI/Paytm payout on Day 7!',
                'perk1_title' => '10 Levels/Day',
                'perk1_subtitle' => 'Daily Target',
                'perk2_title' => 'Bug Reports',
                'perk2_subtitle' => 'Quick Review',
                'perk3_title' => 'Instant Cash',
                'perk3_subtitle' => '₹150 Reward'
            ];
        }

        // If tester program is disabled globally (is_active == 0), automatically filter out tester banners
        if (intval($testerSettings['is_active'] ?? 1) === 0) {
            $banners = array_values(array_filter($banners, function($b) {
                return ($b['action_type'] ?? '') !== 'tester_program';
            }));
        }

        $rewardsEnabled = isset($adSettings['rewards_enabled']) ? intval($adSettings['rewards_enabled']) : 1;

        // Format according to Kotlin App's AppSettingsData class
        $settingsResponse = [
            "status" => "success",
            "data" => [
                "daily_reset" => $dailyReset,
                "user_level" => $userLevel,
                "rewards_enabled" => $rewardsEnabled,
                "ad_settings" => [
                    "id" => intval($adSettings['id'] ?? 1),
                    "interstitial_id" => $adSettings['interstitial_id'] ?? ($adSettings['topon_interstitial_id'] ?? 'b6a6dcd479a322'),
                    "native_id" => $adSettings['native_id'] ?? ($adSettings['topon_native_id'] ?? 'b6a6dcd479b111'),
                    "rewarded_id" => $adSettings['rewarded_id'] ?? ($adSettings['topon_rewarded_id'] ?? 'b6a6dcd479a999'),
                    "topon_app_id" => $adSettings['topon_app_id'] ?? 'h6a6dcd470d298',
                    "topon_app_key" => $adSettings['topon_app_key'] ?? 'adc50c45a5db3969578a75b7843f78507',
                    "topon_splash_id" => $adSettings['topon_splash_id'] ?? 'b6a6dcd4790001',
                    "topon_interstitial_id" => $adSettings['topon_interstitial_id'] ?? 'b6a6dcd479a322',
                    "topon_rewarded_id" => $adSettings['topon_rewarded_id'] ?? 'b6a6dcd479a999',
                    "topon_native_id" => $adSettings['topon_native_id'] ?? 'b6a6dcd479b111',
                    "onesignal_app_id" => $adSettings['onesignal_app_id'] ?? '',
                    "native_enabled" => intval($adSettings['native_enabled'] ?? 0),
                    "rewards_enabled" => $rewardsEnabled,
                    "update_url" => $adSettings['update_url'] ?? '',
                    "update_version" => $adSettings['update_version'] ?? '1.0.0',
                    "update_status" => isset($adSettings['update_status']) ? intval($adSettings['update_status']) : 0
                ],
                "ad_control" => array_map(function($ctrl) {
                    return [
                        "screen_name" => $ctrl['screen_key'] ?? ($ctrl['screen_name'] ?? ''),
                        "ad_type" => $ctrl['ad_type'] ?? 'none'
                    ];
                }, $adControl),
                "reward_settings" => array_map(function($rwd) {
                    return [
                        "required_level" => intval($rwd['required_level']),
                        "reward_amount" => intval($rwd['reward_amount']),
                        "message" => $rwd['message'],
                        "status" => intval($rwd['status'])
                    ];
                }, $rewardSettings),
                "install_tasks" => array_map(function($tsk) {
                    return [
                        "level" => intval($tsk['level']),
                        "required_mb" => intval($tsk['required_mb'])
                    ];
                }, $installTasks),
                "banners" => array_map(function($b) {
                    return [
                        "id" => intval($b['id']),
                        "title" => $b['title'] ?? '',
                        "description" => $b['description'] ?? '',
                        "image_url" => $b['image_url'] ?? '',
                        "action_type" => $b['action_type'] ?? '',
                        "action_value" => $b['action_value'] ?? '',
                        "button_text" => $b['button_text'] ?? ''
                    ];
                }, $banners),
                "tester_program" => [
                    "is_active" => intval($testerSettings['is_active'] ?? 1),
                    "total_days" => intval($testerSettings['total_days'] ?? 7),
                    "reward_amount" => intval($testerSettings['reward_amount'] ?? 150),
                    "instant_approval" => intval($testerSettings['instant_approval'] ?? 1),
                    "badge_text" => $testerSettings['badge_text'] ?? 'EARN ₹150 GUARANTEED',
                    "badge_color" => $testerSettings['badge_color'] ?? '#06D6A0',
                    "title" => $testerSettings['title'] ?? 'Join the 7-Day Testing Team',
                    "description" => $testerSettings['description'] ?? 'Play 10 puzzle levels every day for 7 days, submit short daily bug reviews, and claim direct UPI/Paytm payout on Day 7!',
                    "perk1_title" => $testerSettings['perk1_title'] ?? '10 Levels/Day',
                    "perk1_subtitle" => $testerSettings['perk1_subtitle'] ?? 'Daily Target',
                    "perk2_title" => $testerSettings['perk2_title'] ?? 'Bug Reports',
                    "perk2_subtitle" => $testerSettings['perk2_subtitle'] ?? 'Quick Review',
                    "perk3_title" => $testerSettings['perk3_title'] ?? 'Instant Cash',
                    "perk3_subtitle" => $testerSettings['perk3_subtitle'] ?? '₹150 Reward'
                ],
                "payout_ticker" => getLivePayoutTickerData($db)
            ]
        ];

        respond(true, null, $settingsResponse);
        break;

    case 'save_user':
        // Register or save user device in database
        try {
            $currentLevel = isset($result['current_level']) ? intval($result['current_level']) : 1;
            $referrer = $result['referrer'] ?? '';
            $gid = $result['gid'] ?? '';

            // Log incoming request details for debugging
            logSecurity($deviceId, 'api_request_received', "Action: " . ($action ?? 'unknown') . " | Referrer: " . ($referrer ?: 'empty') . " | GID: " . ($gid ?: 'empty'));

            // Update GID if provided
            if (!empty($gid)) {
                $db->prepare("UPDATE users SET gid = ? WHERE device_id = ?")->execute([$gid, $deviceId]);
                $db->prepare("UPDATE device_keys SET gid = ? WHERE device_id = ?")->execute([$gid, $deviceId]);
            }
            
            // Get user's current level in database
            $userStmt = $db->prepare("SELECT current_level FROM users WHERE device_id = ? LIMIT 1");
            $userStmt->execute([$deviceId]);
            $userRow = $userStmt->fetch(PDO::FETCH_ASSOC);
            $dbLevel = $userRow ? intval($userRow['current_level']) : 0;
            
            // Anti-Cheat: Validate sequential progression (e.g. 1 -> 2 -> 3...)
            // Allow re-saving current level or advancing by exactly 1 level.
            // If they are new ($dbLevel == 0), the maximum level they can jump to is 2.
            $maxAllowedLevel = ($dbLevel == 0) ? 2 : ($dbLevel + 1);
            
            if ($currentLevel > $maxAllowedLevel) {
                // Cheating detected: Block the device
                $db->prepare("UPDATE device_keys SET is_active = 0 WHERE device_id = ?")->execute([$deviceId]);
                $db->prepare("UPDATE users SET is_banned = 1 WHERE device_id = ?")->execute([$deviceId]);
                
                // Record the bypass attempt
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                $db->prepare("INSERT INTO level_bypass_logs (device_id, attempted_level, current_db_level, ip_address) VALUES (?, ?, ?, ?)")
                   ->execute([$deviceId, $currentLevel, $dbLevel, $ip]);
                
                // Log cancelled postback attempts in postback_history for audit
                $campStmt = $db->prepare("SELECT rewardbro_uid, offer_id, event_id FROM user_campaigns WHERE device_id = ?");
                $campStmt->execute([$deviceId]);
                $campaign = $campStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($campaign) {
                    $rewardbroUid = $campaign['rewardbro_uid'];
                    $offerId = $campaign['offer_id'];
                    $eventId = $campaign['event_id'];
                    
                    // Fetch postback settings
                    $settingsStmt = $db->query("SELECT target_level, coin FROM postback_settings ORDER BY target_level ASC");
                    $allSettings = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $completedLevel = $currentLevel - 1;
                    foreach ($allSettings as $settings) {
                        $targetLevel = intval($settings['target_level']);
                        $coinVal = intval($settings['coin'] ?? 0);
                        if ($completedLevel >= $targetLevel) {
                            $historyStmt = $db->prepare("INSERT INTO postback_history (device_id, rewardbro_uid, offer_id, event_id, level, postback_url, response_status, response_body, coin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $historyStmt->execute([
                                $deviceId,
                                $rewardbroUid,
                                $offerId,
                                $eventId,
                                $targetLevel,
                                'CANCELLED_BY_BYPASS',
                                403,
                                "Cancelled: Level jump detected ($dbLevel to $currentLevel). Fake player blocked.",
                                $coinVal
                            ]);
                        }
                    }
                }
                   
                logSecurity($deviceId, 'level_bypass_attempt', "Device tried jumping from level $dbLevel to $currentLevel. Device banned.");
                
                respond(false, "Suspicious level jump detected ($dbLevel to $currentLevel). Device banned.");
            }
            
            // Valid level save: Update users table
            $stmt = $db->prepare("INSERT INTO users (device_id, current_level) VALUES (?, ?) ON DUPLICATE KEY UPDATE current_level = VALUES(current_level)");
            $stmt->execute([$deviceId, $currentLevel]);
            
            // Insert level entry into level history or update timestamp if exists
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $histCheck = $db->prepare("SELECT id FROM level_history WHERE device_id = ? AND level = ? LIMIT 1");
            $histCheck->execute([$deviceId, $currentLevel]);
            $histRow = $histCheck->fetch(PDO::FETCH_ASSOC);
            if ($histRow) {
                $db->prepare("UPDATE level_history SET created_at = NOW(), ip_address = ? WHERE id = ?")
                   ->execute([$ip, $histRow['id']]);
            } else {
                $db->prepare("INSERT INTO level_history (device_id, level, ip_address) VALUES (?, ?, ?)")
                   ->execute([$deviceId, $currentLevel, $ip]);
            }

            // Tester Daily Progress Tracking
            try {
                $testerStmt = $db->prepare("SELECT id, current_day, current_cycle FROM tester_users WHERE device_id = ? AND status = 'active' LIMIT 1");
                $testerStmt->execute([$deviceId]);
                $testerUser = $testerStmt->fetch(PDO::FETCH_ASSOC);
                if ($testerUser) {
                    $curDay = intval($testerUser['current_day']);
                    $curCycle = intval($testerUser['current_cycle']);
                    
                    $progStmt = $db->prepare("SELECT id, target_levels, levels_completed, status FROM tester_daily_progress WHERE device_id = ? AND cycle = ? AND day_number = ? LIMIT 1");
                    $progStmt->execute([$deviceId, $curCycle, $curDay]);
                    $prog = $progStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$prog) {
                        $dayCfgStmt = $db->prepare("SELECT required_levels FROM tester_day_configs WHERE day_number = ? LIMIT 1");
                        $dayCfgStmt->execute([$curDay]);
                        $dayCfg = $dayCfgStmt->fetch(PDO::FETCH_ASSOC);
                        $reqLevels = $dayCfg ? intval($dayCfg['required_levels']) : 10;
                        
                        $db->prepare("INSERT INTO tester_daily_progress (tester_user_id, device_id, cycle, day_number, target_levels, levels_completed, status) VALUES (?, ?, ?, ?, ?, 1, 'in_progress')")
                           ->execute([$testerUser['id'], $deviceId, $curCycle, $curDay, $reqLevels]);
                    } else if ($prog['status'] === 'in_progress') {
                        $newCount = min(intval($prog['target_levels']), intval($prog['levels_completed']) + 1);
                        $db->prepare("UPDATE tester_daily_progress SET levels_completed = ? WHERE id = ?")
                           ->execute([$newCount, $prog['id']]);
                    }
                }
            } catch (Exception $e) {}
            
            // Postback & Campaign Attribution Check
            $campStmt = $db->prepare("SELECT rewardbro_uid, offer_id, event_id, status, created_at FROM user_campaigns WHERE device_id = ?");
            $campStmt->execute([$deviceId]);
            $campaign = $campStmt->fetch(PDO::FETCH_ASSOC);

            // Lazy campaign attribution if not attributed yet (handles existing/reinstalled devices)
            if (!$campaign) {
                $pendingClick = null;
                $isReferrerAttribution = false;
                $isGaidAttribution = false;
                
                // 1. Try direct referrer matching first
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
                    
                    // Set referrer if it was a Referrer or GAID install
                    $saveReferrer = ($isReferrerAttribution || $isGaidAttribution) ? $matchedReferrer : null;

                    // Link device to the campaign
                    $campStmt = $db->prepare("INSERT INTO user_campaigns (device_id, rewardbro_uid, offer_id, event_id, referrer, status, created_at) 
                                             VALUES (?, ?, ?, ?, ?, 'active', NOW()) 
                                             ON DUPLICATE KEY UPDATE rewardbro_uid = VALUES(rewardbro_uid), event_id = VALUES(event_id), referrer = COALESCE(referrer, VALUES(referrer)), status = 'active', created_at = NOW()");
                    $campStmt->execute([$deviceId, $rewardbroUid, $offerId, $eventId, $saveReferrer]);

                    // Reset user level and level history on re-attribution/attribution
                    $db->prepare("UPDATE users SET current_level = 1 WHERE device_id = ?")->execute([$deviceId]);
                    $db->prepare("DELETE FROM level_history WHERE device_id = ? AND level > 1")->execute([$deviceId]);

                    // Update click status to attributed
                    $updateClickStmt = $db->prepare("UPDATE campaign_clicks SET status = 'attributed' WHERE id = ?");
                    $updateClickStmt->execute([$matchedClickId]);

                    // Save rewardbro_uid and referrer in device_keys table
                    $db->prepare("UPDATE device_keys SET rewardbro_uid = ?, referrer = COALESCE(referrer, ?) WHERE device_id = ?")->execute([$rewardbroUid, $saveReferrer, $deviceId]);

                    // Save rewardbro_uid in users table
                    $db->prepare("UPDATE users SET rewardbro_uid = ? WHERE device_id = ?")->execute([$rewardbroUid, $deviceId]);

                    logSecurity($deviceId, 'campaign_attribution_success_lazy', "Attributed to offer: $offerId, user: $rewardbroUid, referrer: " . ($saveReferrer ?? 'NULL') . " | Match Method: " . ($isReferrerAttribution ? 'Referrer Code' : 'GAID'));
                    
                    // Set campaign details so it triggers the postback immediately if they met level target
                    $campaign = [
                        'rewardbro_uid' => $rewardbroUid,
                        'offer_id' => $offerId,
                        'event_id' => $eventId,
                        'status' => 'active',
                        'updated_at' => date('Y-m-d H:i:s'),
                        'referrer' => $saveReferrer
                    ];
                }
            }

            if ($campaign && $campaign['status'] === 'active') {
                // Fetch all configured target levels/events
                $settingsStmt = $db->query("SELECT secret_key, target_level, event_id, coin FROM postback_settings ORDER BY target_level ASC");
                $allSettings = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $completedLevel = $currentLevel - 1;
                $rewardbroUid = $campaign['rewardbro_uid'];
                $offerId = $campaign['offer_id'];
                $eventId = $campaign['event_id'];

                foreach ($allSettings as $settings) {
                    $targetLevel = intval($settings['target_level']);
                    $secretKey = $settings['secret_key'];
                    $settingsEventId = $settings['event_id'] ?? '';
                    $coinVal = intval($settings['coin'] ?? 0);

                    if ($completedLevel >= $targetLevel) {
                        // Check if this level has already been successfully postbacked for this device since the last activation/reset
                        $checkStmt = $db->prepare("SELECT id FROM postback_history WHERE device_id = ? AND level = ? AND created_at >= ? AND response_status != 403");
                        $checkStmt->execute([$deviceId, $targetLevel, $campaign['created_at']]);
                        
                        if (!$checkStmt->fetch()) {
                            // Use settings event_id if configured, otherwise fall back to click event_id
                            $finalEventId = !empty($settingsEventId) ? $settingsEventId : $eventId;

                            // DOUBLE PROTECTION: Verify they actually completed the levels sequentially in history since the campaign started/reset
                            // Skip Level 1 in count to prevent race conditions with automated registration Level 1 insertion.
                            $histCheck = $db->prepare("SELECT COUNT(DISTINCT level) as played_levels FROM level_history WHERE device_id = ? AND level >= 2 AND level <= ? AND created_at >= (SELECT COALESCE(MAX(created_at), '1970-01-01') FROM level_history WHERE device_id = ? AND level = 1)");
                            $histCheck->execute([$deviceId, $targetLevel, $deviceId]);
                            $histRow = $histCheck->fetch(PDO::FETCH_ASSOC);
                            $playedLevels = $histRow ? intval($histRow['played_levels']) : 0;
                            
                            $expectedLevels = $targetLevel - 1;
                            
                            if ($playedLevels < $expectedLevels) {
                                // Fetch detailed history of played levels to log details
                                $detailsQuery = $db->prepare("SELECT level, created_at FROM level_history WHERE device_id = ? AND level <= ? ORDER BY level ASC");
                                $detailsQuery->execute([$deviceId, $targetLevel]);
                                $detailsList = $detailsQuery->fetchAll(PDO::FETCH_ASSOC);
                                $detailsStr = "";
                                foreach ($detailsList as $d) {
                                    $detailsStr .= "L" . $d['level'] . ":" . $d['created_at'] . "; ";
                                }

                                $msg = "Cancelled: Incomplete level history. Played $playedLevels of $expectedLevels levels (excluding Level 1) since campaign start (" . $campaign['created_at'] . "). Details: " . $detailsStr;

                                // Block the postback and log it as cancelled/fake in postback_history
                                $historyStmt = $db->prepare("INSERT INTO postback_history (device_id, rewardbro_uid, offer_id, event_id, level, postback_url, response_status, response_body, coin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $historyStmt->execute([
                                    $deviceId,
                                    $rewardbroUid,
                                    $offerId,
                                    $finalEventId,
                                    $targetLevel,
                                    'CANCELLED_INCOMPLETE_HISTORY',
                                    403,
                                    $msg,
                                    $coinVal
                                ]);
                                continue; // Skip firing this postback
                            }

                            // Format the postback URL
                            $postbackUrl = "https://app.rewardbro.in/cpa-postback?userId=" . urlencode($rewardbroUid) . 
                                           "&appName=rewardbro" . 
                                           "&offerId=" . urlencode($offerId) . 
                                           "&secret=" . urlencode($secretKey) . 
                                           "&eventId=" . urlencode($finalEventId);
                                          
                            // Trigger secure server-to-server GET request
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
                            
                            // Log postback execution (saving the fired event_id)
                            $historyStmt = $db->prepare("INSERT INTO postback_history (device_id, rewardbro_uid, offer_id, event_id, level, postback_url, response_status, response_body, coin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $historyStmt->execute([$deviceId, $rewardbroUid, $offerId, $finalEventId, $targetLevel, $postbackUrl, $responseStatus, $responseBody, $coinVal]);
                            
                            logSecurity($deviceId, 'postback_triggered', "Successfully fired postback for level $targetLevel (status $responseStatus)");

                            // Only mark campaign as completed if the postback for the max level was successfully fired (HTTP status 2xx)
                            $maxSettingsStmt = $db->query("SELECT MAX(target_level) as max_level FROM postback_settings");
                            $maxLevelRow = $maxSettingsStmt->fetch();
                            $maxLevel = $maxLevelRow ? intval($maxLevelRow['max_level']) : 0;

                            if ($maxLevel > 0 && $targetLevel === $maxLevel && intval($responseStatus) >= 200 && intval($responseStatus) < 300) {
                                $updateCampStmt = $db->prepare("UPDATE user_campaigns SET status = 'completed' WHERE device_id = ? AND offer_id = ?");
                                $updateCampStmt->execute([$deviceId, $offerId]);
                            }
                        }
                    }
                }
            }
            
            respond(true, null, ["status" => "success"]);
        } catch (Exception $e) {
            respond(false, "Failed to save user: " . $e->getMessage());
        }
        break;

    case 'claim_reward':
        // Claim reward action
        $method = $result['method'] ?? '';
        $account = $result['account'] ?? '';
        $amount = intval($result['amount'] ?? 0);

        if (empty($method) || empty($account) || $amount <= 0) {
            respond(false, "Invalid reward fields");
        }

        try {
            // First, make sure the user exists
            $db->prepare("INSERT IGNORE INTO users (device_id) VALUES (?)")->execute([$deviceId]);

            // Save reward claim record
            $stmt = $db->prepare("INSERT INTO rewards (device_id, method, account, amount, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$deviceId, $method, $account, $amount]);
            respond(true, null, ["status" => "success"]);
        } catch (Exception $e) {
            respond(false, "Failed to claim reward: " . $e->getMessage());
        }
        break;

    case 'get_reward_history':
    case 'get_rewards_history':
        try {
            $allClaims = [];

            // 1. Fetch tester claims
            try {
                $tStmt = $db->prepare("SELECT id, 'tester' as type, cycle, amount, payment_method as method, account_details as account, COALESCE(voucher_code, '') as voucher_code, COALESCE(admin_notes, '') as admin_notes, status, created_at FROM tester_reward_claims WHERE device_id = ? ORDER BY id DESC");
                $tStmt->execute([$deviceId]);
                $testerClaims = $tStmt->fetchAll(PDO::FETCH_ASSOC);
                if ($testerClaims) {
                    $allClaims = array_merge($allClaims, $testerClaims);
                }
            } catch (Exception $e) {
                try {
                    $tStmt = $db->prepare("SELECT id, 'tester' as type, cycle, amount, payment_method as method, account_details as account, '' as voucher_code, '' as admin_notes, status, created_at FROM tester_reward_claims WHERE device_id = ? ORDER BY id DESC");
                    $tStmt->execute([$deviceId]);
                    $testerClaims = $tStmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($testerClaims) {
                        $allClaims = array_merge($allClaims, $testerClaims);
                    }
                } catch (Exception $e2) {}
            }

            // 2. Fetch milestone claims
            try {
                $mStmt = $db->prepare("SELECT id, 'milestone' as type, 1 as cycle, amount, method, account, COALESCE(voucher_code, '') as voucher_code, COALESCE(admin_notes, '') as admin_notes, status, created_at FROM rewards WHERE device_id = ? ORDER BY id DESC");
                $mStmt->execute([$deviceId]);
                $milestoneClaims = $mStmt->fetchAll(PDO::FETCH_ASSOC);
                if ($milestoneClaims) {
                    $allClaims = array_merge($allClaims, $milestoneClaims);
                }
            } catch (Exception $e) {
                try {
                    $mStmt = $db->prepare("SELECT id, 'milestone' as type, 1 as cycle, amount, method, account, '' as voucher_code, '' as admin_notes, status, created_at FROM rewards WHERE device_id = ? ORDER BY id DESC");
                    $mStmt->execute([$deviceId]);
                    $milestoneClaims = $mStmt->fetchAll(PDO::FETCH_ASSOC);
                    if ($milestoneClaims) {
                        $allClaims = array_merge($allClaims, $milestoneClaims);
                    }
                } catch (Exception $e2) {}
            }

            usort($allClaims, function($a, $b) {
                $tA = !empty($a['created_at']) ? strtotime($a['created_at']) : 0;
                $tB = !empty($b['created_at']) ? strtotime($b['created_at']) : 0;
                return $tB - $tA;
            });

            respond(true, null, [
                "status" => "success",
                "history" => $allClaims,
                "claims" => $allClaims
            ]);
        } catch (Exception $e) {
            respond(false, "Failed to fetch history: " . $e->getMessage());
        }
        break;

    case 'register_tester':
        $name = trim($result['name'] ?? '');
        $mobile = trim($result['mobile'] ?? '');
        $email = trim($result['email'] ?? '');

        if (empty($name) || empty($mobile) || empty($email)) {
            respond(false, "Please provide Name, Mobile, and Email");
        }

        try {
            $setStmt = $db->query("SELECT instant_approval, is_active FROM tester_settings WHERE id = 1 LIMIT 1");
            $tSetting = $setStmt ? $setStmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($tSetting && intval($tSetting['is_active']) === 0) {
                respond(false, "Tester Program is currently closed or paused by admin.");
                break;
            }
            $instant = $tSetting ? (intval($tSetting['instant_approval']) === 1) : true;
            $initialStatus = $instant ? 'active' : 'pending';
            $approvedAt = $instant ? date('Y-m-d H:i:s') : null;

            $checkStmt = $db->prepare("SELECT id, status, current_day, current_cycle FROM tester_users WHERE device_id = ? LIMIT 1");
            $checkStmt->execute([$deviceId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $statusToSet = ($existing['status'] === 'active' || $existing['status'] === 'completed') ? $existing['status'] : $initialStatus;
                $db->prepare("UPDATE tester_users SET name = ?, mobile = ?, email = ?, status = ?, approved_at = COALESCE(approved_at, ?) WHERE id = ?")
                   ->execute([$name, $mobile, $email, $statusToSet, $approvedAt, $existing['id']]);
                $testerId = $existing['id'];
                $finalStatus = $statusToSet;
                $curDay = intval($existing['current_day']);
                $curCycle = intval($existing['current_cycle']);
            } else {
                $insStmt = $db->prepare("INSERT INTO tester_users (device_id, name, mobile, email, status, current_day, current_cycle, registered_at, approved_at) VALUES (?, ?, ?, ?, ?, 1, 1, NOW(), ?)");
                $insStmt->execute([$deviceId, $name, $mobile, $email, $initialStatus, $approvedAt]);
                $testerId = $db->lastInsertId();
                $finalStatus = $initialStatus;
                $curDay = 1;
                $curCycle = 1;
            }

            if ($finalStatus === 'active') {
                $dayCfgStmt = $db->prepare("SELECT required_levels FROM tester_day_configs WHERE day_number = 1 LIMIT 1");
                $dayCfgStmt->execute();
                $dayCfg = $dayCfgStmt->fetch(PDO::FETCH_ASSOC);
                $target1 = $dayCfg ? intval($dayCfg['required_levels']) : 10;

                $db->prepare("INSERT IGNORE INTO tester_daily_progress (tester_user_id, device_id, cycle, day_number, target_levels, levels_completed, status) VALUES (?, ?, ?, 1, ?, 0, 'in_progress')")
                   ->execute([$testerId, $deviceId, $curCycle, $target1]);
            }

            respond(true, null, [
                "status" => "success",
                "tester_status" => $finalStatus,
                "instant_approved" => $instant
            ]);
        } catch (Exception $e) {
            respond(false, "Registration failed: " . $e->getMessage());
        }
        break;

    case 'get_tester_status':
        try {
            $userStmt = $db->prepare("SELECT * FROM tester_users WHERE device_id = ? LIMIT 1");
            $userStmt->execute([$deviceId]);
            $testerUser = $userStmt->fetch(PDO::FETCH_ASSOC);

            $setStmt = $db->query("SELECT total_days, reward_amount, is_active FROM tester_settings WHERE id = 1 LIMIT 1");
            $tSetting = $setStmt ? $setStmt->fetch(PDO::FETCH_ASSOC) : ['total_days' => 7, 'reward_amount' => 150, 'is_active' => 1];

            if (!$testerUser) {
                respond(true, null, [
                    "registered" => false,
                    "status" => "not_registered",
                    "reward_amount" => intval($tSetting['reward_amount']),
                    "total_days" => intval($tSetting['total_days']),
                    "is_program_active" => intval($tSetting['is_active']),
                    "payout_ticker" => getLivePayoutTickerData($db)
                ]);
                break;
            }

            $curCycle = intval($testerUser['current_cycle']);
            $userStatus = $testerUser['status']; // 'pending', 'active', 'rejected', 'completed'

            $cfgStmt = $db->query("SELECT day_number, required_levels, title, instructions FROM tester_day_configs WHERE status = 1 ORDER BY day_number ASC");
            $allConfigs = $cfgStmt ? $cfgStmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $progStmt = $db->prepare("SELECT day_number, target_levels, levels_completed, feedback_text, status, completed_at FROM tester_daily_progress WHERE device_id = ? AND cycle = ?");
            $progStmt->execute([$deviceId, $curCycle]);
            $progMap = [];
            while ($pRow = $progStmt->fetch(PDO::FETCH_ASSOC)) {
                $progMap[intval($pRow['day_number'])] = $pRow;
            }

            // Determine client timezone and if a mission was completed TODAY
            $clientTimezone = getSecureClientTimezone($result['device_info'] ?? '');
            $tzObj = new DateTimeZone($clientTimezone ?: 'Asia/Kolkata');
            $nowObj = new DateTime('now', $tzObj);
            $todayStr = $nowObj->format('Y-m-d');

            $lastCompletedDayNum = 0;
            $isCompletedToday = false;

            foreach ($progMap as $dNum => $pRow) {
                if ($pRow['status'] === 'completed' && !empty($pRow['completed_at'])) {
                    if ($dNum > $lastCompletedDayNum) {
                        $lastCompletedDayNum = $dNum;
                    }
                    try {
                        $compDt = new DateTime($pRow['completed_at'], new DateTimeZone('UTC'));
                        $compDt->setTimeZone($tzObj);
                        if ($compDt->format('Y-m-d') === $todayStr) {
                            $isCompletedToday = true;
                        }
                    } catch (Exception $e) {}
                }
            }

            $totalDays = intval($tSetting['total_days']);
            $activeDayNum = min($totalDays, $lastCompletedDayNum + 1);

            $claimStmt = $db->prepare("SELECT id, status, amount, payment_method, account_details, voucher_code, admin_notes, created_at FROM tester_reward_claims WHERE device_id = ? AND cycle = ? LIMIT 1");
            $claimStmt->execute([$deviceId, $curCycle]);
            $existingClaim = $claimStmt->fetch(PDO::FETCH_ASSOC);

            $daysList = [];
            $cumulativeLevel = 1;
            foreach ($allConfigs as $cfg) {
                $dNum = intval($cfg['day_number']);
                $p = $progMap[$dNum] ?? null;
                $target = $p ? intval($p['target_levels']) : intval($cfg['required_levels']);
                $completed = $p ? intval($p['levels_completed']) : 0;
                $hasFeedback = $p && !empty($p['feedback_text']);
                
                $isCompleted = ($dNum <= $lastCompletedDayNum);
                $isDayActive = ($dNum === $activeDayNum && !$isCompletedToday && !$isCompleted);
                $unlocksTomorrow = ($dNum === $activeDayNum && $isCompletedToday && !$isCompleted);
                
                $dayStatus = 'locked';
                if ($isCompleted) {
                    $dayStatus = 'completed';
                } elseif ($isDayActive) {
                    $dayStatus = 'in_progress';
                }

                $startLevel = $cumulativeLevel;
                $endLevel = $startLevel + $target - 1;
                $cumulativeLevel += $target;

                $daysList[] = [
                    "day_number" => $dNum,
                    "title" => $cfg['title'],
                    "instructions" => $cfg['instructions'] ?? '',
                    "start_level" => $startLevel,
                    "end_level" => $endLevel,
                    "target_levels" => $target,
                    "levels_completed" => $completed,
                    "status" => $dayStatus,
                    "is_completed" => $isCompleted,
                    "unlocks_tomorrow" => $unlocksTomorrow,
                    "feedback_submitted" => $hasFeedback,
                    "feedback_text" => $p['feedback_text'] ?? ''
                ];
            }

            $activeProg = $progMap[$activeDayNum] ?? null;
            $activeTarget = $activeProg ? intval($activeProg['target_levels']) : 10;
            $activeCompleted = $activeProg ? intval($activeProg['levels_completed']) : 0;
            
            // Ready for review ONLY if active today, completed target levels, and not yet reviewed
            $curDayReadyForReview = (!$isCompletedToday && $activeCompleted >= $activeTarget && ($activeProg['status'] ?? '') !== 'completed');

            $allDaysCompleted = ($lastCompletedDayNum >= $totalDays);
            $canClaim = ($userStatus === 'completed' || $allDaysCompleted) && !$existingClaim;

            // Fetch configured payout methods
            $mStmt = $db->query("SELECT name, input_placeholder FROM tester_payout_methods WHERE status = 1 ORDER BY sort_order ASC, id ASC");
            $payoutMethods = $mStmt ? $mStmt->fetchAll(PDO::FETCH_ASSOC) : [];
            if (empty($payoutMethods)) {
                $payoutMethods = [
                    ["name" => "UPI", "input_placeholder" => "Enter UPI ID (e.g. name@okhdfcbank)"],
                    ["name" => "Paytm", "input_placeholder" => "Enter 10-digit Paytm Wallet/Mobile Number"],
                    ["name" => "Google Play", "input_placeholder" => "Enter Gmail Address for Redeem Code"],
                    ["name" => "Amazon Pay", "input_placeholder" => "Enter Amazon registered Mobile or Email"]
                ];
            }

            respond(true, null, [
                "registered" => true,
                "status" => $userStatus,
                "is_finished" => (isset($testerUser['is_finished']) && intval($testerUser['is_finished']) === 1),
                "current_day" => $activeDayNum,
                "current_cycle" => $curCycle,
                "is_today_completed" => $isCompletedToday,
                "last_completed_day" => $lastCompletedDayNum,
                "user" => [
                    "name" => $testerUser['name'],
                    "mobile" => $testerUser['mobile'],
                    "email" => $testerUser['email'],
                    "registered_at" => $testerUser['registered_at']
                ],
                "days" => $daysList,
                "payout_methods" => $payoutMethods,
                "current_day_ready_for_review" => $curDayReadyForReview,
                "can_claim" => $canClaim,
                "has_claimed" => ($existingClaim !== false),
                "claim_info" => $existingClaim ?: null,
                "reward_amount" => intval($tSetting['reward_amount']),
                "total_days" => $totalDays,
                "payout_ticker" => getLivePayoutTickerData($db)
            ]);
        } catch (Exception $e) {
            respond(false, "Failed to get tester status: " . $e->getMessage());
        }
        break;

    case 'sync_tester_level':
        $dayNumber = intval($result['day_number'] ?? 0);
        $levelNumber = intval($result['level_number'] ?? 0);

        try {
            $userStmt = $db->prepare("SELECT id, current_day, current_cycle, status FROM tester_users WHERE device_id = ? LIMIT 1");
            $userStmt->execute([$deviceId]);
            $testerUser = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$testerUser || $testerUser['status'] !== 'active') {
                respond(false, "User not active in tester program");
            }

            $curCycle = intval($testerUser['current_cycle']);

            // Fetch progress for this cycle
            $progStmt = $db->prepare("SELECT day_number, completed_at, status FROM tester_daily_progress WHERE device_id = ? AND cycle = ?");
            $progStmt->execute([$deviceId, $curCycle]);
            
            $clientTimezone = getSecureClientTimezone($result['device_info'] ?? '');
            $tzObj = new DateTimeZone($clientTimezone ?: 'Asia/Kolkata');
            $nowObj = new DateTime('now', $tzObj);
            $todayStr = $nowObj->format('Y-m-d');

            $lastCompletedDayNum = 0;
            $isCompletedToday = false;
            while ($pRow = $progStmt->fetch(PDO::FETCH_ASSOC)) {
                if ($pRow['status'] === 'completed' && !empty($pRow['completed_at'])) {
                    $d = intval($pRow['day_number']);
                    if ($d > $lastCompletedDayNum) {
                        $lastCompletedDayNum = $d;
                    }
                    try {
                        $compDt = new DateTime($pRow['completed_at'], new DateTimeZone('UTC'));
                        $compDt->setTimeZone($tzObj);
                        if ($compDt->format('Y-m-d') === $todayStr) {
                            $isCompletedToday = true;
                        }
                    } catch (Exception $e) {}
                }
            }

            $activeDayNum = $lastCompletedDayNum + 1;

            if ($isCompletedToday) {
                respond(false, "You have already completed today's tester mission! Day $activeDayNum will unlock tomorrow.");
            }

            $targetDay = ($dayNumber > 0) ? $dayNumber : $activeDayNum;
            if ($targetDay !== $activeDayNum) {
                respond(false, "Only Day $activeDayNum is active today.");
            }

            $progStmt2 = $db->prepare("SELECT id, target_levels, levels_completed, status FROM tester_daily_progress WHERE device_id = ? AND cycle = ? AND day_number = ? LIMIT 1");
            $progStmt2->execute([$deviceId, $curCycle, $targetDay]);
            $prog = $progStmt2->fetch(PDO::FETCH_ASSOC);

            $dayCfgStmt = $db->prepare("SELECT required_levels FROM tester_day_configs WHERE day_number = ? LIMIT 1");
            $dayCfgStmt->execute([$targetDay]);
            $dayCfg = $dayCfgStmt->fetch(PDO::FETCH_ASSOC);
            $targetLevels = $dayCfg ? intval($dayCfg['required_levels']) : 10;

            if (!$prog) {
                $completed = 1;
                $db->prepare("INSERT INTO tester_daily_progress (tester_user_id, device_id, cycle, day_number, target_levels, levels_completed, status) VALUES (?, ?, ?, ?, ?, 1, 'in_progress')")
                   ->execute([$testerUser['id'], $deviceId, $curCycle, $targetDay, $targetLevels]);
            } else {
                $completed = min($targetLevels, intval($prog['levels_completed']) + 1);
                $db->prepare("UPDATE tester_daily_progress SET levels_completed = ? WHERE id = ?")
                   ->execute([$completed, $prog['id']]);
            }

            $isDayCompleted = ($completed >= $targetLevels);

            respond(true, null, [
                "status" => "success",
                "day_number" => $targetDay,
                "levels_completed" => $completed,
                "target_levels" => $targetLevels,
                "is_day_completed" => $isDayCompleted
            ]);
        } catch (Exception $e) {
            respond(false, "Failed to sync tester level: " . $e->getMessage());
        }
        break;

    case 'submit_tester_review':
    case 'submit_tester_feedback':
        $dayNumber = intval($result['day_number'] ?? 0);
        $feedbackText = trim($result['feedback_text'] ?? $result['review_text'] ?? $result['feedback'] ?? '');
        $rating = intval($result['rating'] ?? 5);

        if (empty($feedbackText)) {
            respond(false, "Feedback text is required");
        }

        try {
            $userStmt = $db->prepare("SELECT id, current_day, current_cycle, status FROM tester_users WHERE device_id = ? LIMIT 1");
            $userStmt->execute([$deviceId]);
            $testerUser = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$testerUser) {
                respond(false, "Tester user not found");
            }

            $curCycle = intval($testerUser['current_cycle']);

            $progStmt = $db->prepare("SELECT id, target_levels, levels_completed FROM tester_daily_progress WHERE device_id = ? AND cycle = ? AND day_number = ? LIMIT 1");
            $progStmt->execute([$deviceId, $curCycle, $dayNumber]);
            $prog = $progStmt->fetch(PDO::FETCH_ASSOC);

            if (!$prog) {
                respond(false, "No progress recorded for Day " . $dayNumber);
            }

            $targetLevels = intval($prog['target_levels']);
            $levelsCompleted = intval($prog['levels_completed']);

            if ($levelsCompleted < $targetLevels) {
                respond(false, "You must complete all $targetLevels levels for Day $dayNumber before submitting feedback.");
            }

            // Save feedback and mark completed
            $db->prepare("UPDATE tester_daily_progress SET feedback_text = ?, status = 'completed', completed_at = NOW() WHERE id = ?")
               ->execute(["Rating: {$rating}/5 | " . $feedbackText, $prog['id']]);

            // Check total days
            $setStmt = $db->query("SELECT total_days FROM tester_settings WHERE id = 1 LIMIT 1");
            $totalDays = ($setRow = $setStmt->fetch()) ? intval($setRow['total_days']) : 7;

            if ($dayNumber >= $totalDays) {
                $db->prepare("UPDATE tester_users SET status = 'completed', completed_at = NOW() WHERE id = ?")
                   ->execute([$testerUser['id']]);
            } else {
                $nextDay = $dayNumber + 1;
                $db->prepare("UPDATE tester_users SET current_day = ? WHERE id = ?")
                   ->execute([$nextDay, $testerUser['id']]);

                // Create row for next day
                $dayCfgStmt = $db->prepare("SELECT required_levels FROM tester_day_configs WHERE day_number = ? LIMIT 1");
                $dayCfgStmt->execute([$nextDay]);
                $dayCfg = $dayCfgStmt->fetch(PDO::FETCH_ASSOC);
                $targetNext = $dayCfg ? intval($dayCfg['required_levels']) : 10;

                $db->prepare("INSERT IGNORE INTO tester_daily_progress (tester_user_id, device_id, cycle, day_number, target_levels, levels_completed, status) VALUES (?, ?, ?, ?, ?, 0, 'in_progress')")
                   ->execute([$testerUser['id'], $deviceId, $curCycle, $nextDay, $targetNext]);
            }

            respond(true, null, [
                "status" => "success",
                "day_number" => $dayNumber,
                "all_completed" => ($dayNumber >= $totalDays)
            ]);
        } catch (Exception $e) {
            respond(false, "Failed to submit feedback: " . $e->getMessage());
        }
        break;

    case 'submit_tester_reward_claim':
    case 'claim_tester_reward':
        $method = trim($result['payment_method'] ?? $result['method'] ?? 'UPI');
        $account = trim($result['account_details'] ?? $result['account'] ?? '');

        if (empty($account)) {
            respond(false, "Payment details are required!");
        }

        try {
            $userStmt = $db->prepare("SELECT id, current_cycle FROM tester_users WHERE device_id = ? LIMIT 1");
            $userStmt->execute([$deviceId]);
            $testerUser = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$testerUser) {
                respond(false, "Tester user not found");
            }

            $setStmt = $db->query("SELECT reward_amount FROM tester_settings WHERE id = 1 LIMIT 1");
            $rewardAmount = ($setRow = $setStmt->fetch()) ? intval($setRow['reward_amount']) : 150;

            $curCycle = intval($testerUser['current_cycle']);

            $checkClaim = $db->prepare("SELECT id FROM tester_reward_claims WHERE device_id = ? AND cycle = ? LIMIT 1");
            $checkClaim->execute([$deviceId, $curCycle]);
            if ($checkClaim->fetch()) {
                respond(false, "Reward for this cycle has already been claimed!");
            }

            $stmt = $db->prepare("INSERT INTO tester_reward_claims (tester_user_id, device_id, cycle, amount, payment_method, account_details, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$testerUser['id'], $deviceId, $curCycle, $rewardAmount, $method, $account]);

            respond(true, null, ["status" => "success"]);
        } catch (Exception $e) {
            respond(false, "Failed to claim tester reward: " . $e->getMessage());
        }
        break;

    case 'reset_tester_cycle':
        try {
            $userStmt = $db->prepare("SELECT id, current_cycle FROM tester_users WHERE device_id = ? LIMIT 1");
            $userStmt->execute([$deviceId]);
            $testerUser = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$testerUser) {
                respond(false, "Tester user not found");
            }

            $nextCycle = intval($testerUser['current_cycle']) + 1;
            $db->prepare("UPDATE tester_users SET current_cycle = ?, current_day = 1, status = 'active', is_finished = 0, completed_at = NULL WHERE id = ?")
               ->execute([$nextCycle, $testerUser['id']]);

            $dayCfgStmt = $db->prepare("SELECT required_levels FROM tester_day_configs WHERE day_number = 1 LIMIT 1");
            $dayCfgStmt->execute([1]);
            $dayCfg = $dayCfgStmt->fetch(PDO::FETCH_ASSOC);
            $target1 = $dayCfg ? intval($dayCfg['required_levels']) : 10;

            $db->prepare("INSERT INTO tester_daily_progress (tester_user_id, device_id, cycle, day_number, target_levels, levels_completed, status) VALUES (?, ?, ?, 1, ?, 0, 'in_progress')")
               ->execute([$testerUser['id'], $deviceId, $nextCycle, $target1]);

            respond(true, null, ["status" => "success", "new_cycle" => $nextCycle]);
        } catch (Exception $e) {
            respond(false, "Failed to restart cycle: " . $e->getMessage());
        }
        break;

    case 'end_tester_program':
        try {
            $userStmt = $db->prepare("SELECT id FROM tester_users WHERE device_id = ? LIMIT 1");
            $userStmt->execute([$deviceId]);
            $testerUser = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$testerUser) {
                respond(false, "Tester user not found");
            }

            $db->prepare("UPDATE tester_users SET is_finished = 1, status = 'completed' WHERE id = ?")
               ->execute([$testerUser['id']]);

            respond(true, null, ["status" => "success", "is_finished" => true]);
        } catch (Exception $e) {
            respond(false, "Failed to end tester program: " . $e->getMessage());
        }
        break;

    default:
        respond(false, "Unknown action: " . $action);
        break;
}
?>
