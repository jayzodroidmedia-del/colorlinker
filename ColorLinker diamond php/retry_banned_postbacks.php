<?php
// 1. Config and Database connection
require_once __DIR__ . '/config.php';

$db = getDB();
$isCli = (php_sapi_name() === 'cli');

// 2. Fetch all banned users (either is_banned=1 in users or is_active=0 in device_keys)
$bannedQuery = $db->query("
    SELECT DISTINCT u.device_id, u.current_level, dk.is_active, u.is_banned
    FROM users u
    LEFT JOIN device_keys dk ON u.device_id = dk.device_id
    WHERE u.is_banned = 1 OR dk.is_active = 0
");

$bannedUsers = $bannedQuery->fetchAll(PDO::FETCH_ASSOC);
$totalBanned = count($bannedUsers);

$results = [];

// 3. Retrieve postback settings
$settingsStmt = $db->query("SELECT secret_key, target_level, event_id, coin FROM postback_settings ORDER BY target_level ASC");
$postbackSettings = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($bannedUsers as $user) {
    $deviceId = $user['device_id'];
    $currentLvl = intval($user['current_level']);
    
    // Count distinct levels completed by this user in level_history
    $histStmt = $db->prepare("SELECT COUNT(DISTINCT level) as completed_count FROM level_history WHERE device_id = ? AND level <= ?");
    $histStmt->execute([$deviceId, $currentLvl]);
    $histData = $histStmt->fetch();
    $completedCount = $histData ? intval($histData['completed_count']) : 0;
    
    // Check if the level history matches their current level (allowing a difference of 1 for level starting offsets)
    // If they completed at least (current_level - 1) distinct levels, their progress is considered sequential and legitimate.
    $isLegitimate = ($completedCount >= ($currentLvl - 1));
    
    $actionsTaken = [];
    $statusType = '';
    
    if ($isLegitimate) {
        // UNBAN: Set is_banned=0 and is_active=1
        $unbanStmt1 = $db->prepare("UPDATE users SET is_banned = 0 WHERE device_id = ?");
        $unbanStmt1->execute([$deviceId]);
        
        $unbanStmt2 = $db->prepare("UPDATE device_keys SET is_active = 1 WHERE device_id = ?");
        $unbanStmt2->execute([$deviceId]);
        
        $statusType = 'Legitimate User (Unbanned) 🟢';
        $actionsTaken[] = "Unbanned account";
        
        // Check if user is associated with a campaign to process postbacks
        $campStmt = $db->prepare("SELECT rewardbro_uid, offer_id, event_id, status FROM user_campaigns WHERE device_id = ?");
        $campStmt->execute([$deviceId]);
        $campaign = $campStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($campaign) {
            $rewardbroUid = $campaign['rewardbro_uid'];
            $offerId = $campaign['offer_id'];
            $clickEventId = $campaign['event_id'];
            $completedLevel = $currentLvl - 1;
            
            foreach ($postbackSettings as $settings) {
                $targetLevel = intval($settings['target_level']);
                $secretKey = $settings['secret_key'];
                $settingsEventId = $settings['event_id'] ?? '';
                $coinVal = intval($settings['coin'] ?? 0);
                
                if ($completedLevel >= $targetLevel) {
                    // Check if a successful postback already exists for this device and level
                    $checkStmt = $db->prepare("SELECT id FROM postback_history WHERE device_id = ? AND level = ? AND response_status >= 200 AND response_status < 300");
                    $checkStmt->execute([$deviceId, $targetLevel]);
                    
                    if (!$checkStmt->fetch()) {
                        // Trigger the postback
                        $finalEventId = !empty($settingsEventId) ? $settingsEventId : $clickEventId;
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
                        
                        // Log postback
                        $historyStmt = $db->prepare("INSERT INTO postback_history (device_id, rewardbro_uid, offer_id, event_id, level, postback_url, response_status, response_body, coin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $historyStmt->execute([$deviceId, $rewardbroUid, $offerId, $finalEventId, $targetLevel, $postbackUrl, $responseStatus, $responseBody, $coinVal]);
                        
                        $actionsTaken[] = "Fired level $targetLevel postback (Status: $responseStatus)";
                    } else {
                        $actionsTaken[] = "Level $targetLevel postback already successful";
                    }
                }
            }
        } else {
            $actionsTaken[] = "Organic user (no campaign attribution)";
        }
    } else {
        $statusType = 'Cheater / Incomplete History (Kept Banned) 🔴';
        $actionsTaken[] = "Bypassed levels (Played $completedCount of $currentLvl levels)";
    }
    
    $results[] = [
        'device_id' => $deviceId,
        'current_level' => $currentLvl,
        'completed_count' => $completedCount,
        'status' => $statusType,
        'details' => implode(', ', $actionsTaken)
    ];
}

// 4. Output rendering
if ($isCli) {
    echo "=== BANNED USERS SYSTEM RETRY & UNBAN REPORT ===\n";
    echo "Total banned records analyzed: $totalBanned\n\n";
    printf("%-20s | %-6s | %-16s | %-30s | %s\n", "Device ID", "Level", "History Played", "Status", "Actions taken");
    echo str_repeat("-", 100) . "\n";
    foreach ($results as $r) {
        printf("%-20s | %-6d | %-16d | %-30s | %s\n", $r['device_id'], $r['current_level'], $r['completed_count'], $r['status'], $r['details']);
    }
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Banned Users Verification & Unban Report</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Inter', sans-serif;
                background-color: #fafafa;
                color: #262626;
                padding: 40px 20px;
                margin: 0;
            }
            .container {
                max-width: 1200px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border-radius: 12px;
                border: 1px solid #e5e5e5;
                box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            }
            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 2px solid #f3f4f6;
                padding-bottom: 20px;
                margin-bottom: 20px;
            }
            h1 {
                margin: 0;
                font-weight: 700;
                font-size: 22px;
                color: #4b5563;
            }
            .summary {
                font-weight: 500;
                color: #6b7280;
                font-size: 14px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 10px;
            }
            th, td {
                padding: 12px 15px;
                text-align: left;
                border-bottom: 1px solid #f3f4f6;
            }
            th {
                background-color: #f3f4f6;
                font-weight: 600;
                color: #374151;
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
            }
            tr:hover {
                background-color: #fafafa;
            }
            .badge {
                padding: 4px 8px;
                border-radius: 9999px;
                font-size: 12px;
                font-weight: 600;
                display: inline-block;
            }
            .badge-success {
                background-color: #d1fae5;
                color: #065f46;
            }
            .badge-failed {
                background-color: #fee2e2;
                color: #991b1b;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Banned Users Verification & Unban Report</h1>
                <div class="summary">
                    Total banned users analyzed: <strong><?php echo $totalBanned; ?></strong>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Device ID</th>
                        <th>Current Level (DB)</th>
                        <th>History Count</th>
                        <th>Verification Status</th>
                        <th>Actions Taken & Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #9ca3af; padding: 40px; font-weight: 500;">
                                No banned users found in database.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td><code><?php echo $r['device_id']; ?></code></td>
                                <td><strong>Level <?php echo $r['current_level']; ?></strong></td>
                                <td><?php echo $r['completed_count']; ?> levels played</td>
                                <td>
                                    <?php if (strpos($r['status'], 'Legitimate') !== false): ?>
                                        <span class="badge badge-success">Legitimate (Unbanned)</span>
                                    <?php else: ?>
                                        <span class="badge badge-failed">Cheater (Banned)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 13px; color: #4b5563;">
                                    <?php echo $r['details']; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </body>
    </html>
    <?php
}
?>
