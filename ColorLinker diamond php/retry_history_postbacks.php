<?php
// 1. Config and Database connection
require_once __DIR__ . '/config.php';

// Authentication Pin Check
$pin = $_GET['pin'] ?? '';
if ($pin !== 'mint3') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied. Pass the correct ?pin= to access.");
}

$db = getDB();
$isCli = (php_sapi_name() === 'cli');

// 2. Fetch all failed/cancelled postbacks due to incomplete level history
$failedQuery = $db->query("
    SELECT id, device_id, rewardbro_uid, offer_id, event_id, level, response_body, coin, created_at
    FROM postback_history
    WHERE response_status = 403 
      AND (postback_url = 'CANCELLED_INCOMPLETE_HISTORY' OR response_body LIKE '%Incomplete level history%')
    ORDER BY id DESC
");

$failedPostbacks = $failedQuery->fetchAll(PDO::FETCH_ASSOC);
$totalScanned = count($failedPostbacks);

// Retrieve postback settings
$settingsStmt = $db->query("SELECT secret_key, target_level, event_id, coin FROM postback_settings ORDER BY target_level ASC");
$postbackSettings = $settingsStmt->fetchAll(PDO::FETCH_ASSOC);

$results = [];
$totalRetried = 0;

foreach ($failedPostbacks as $row) {
    $recordId = $row['id'];
    $deviceId = $row['device_id'];
    $rewardbroUid = $row['rewardbro_uid'];
    $offerId = $row['offer_id'];
    $eventId = $row['event_id'];
    $coin = intval($row['coin'] ?? 0);
    
    // Check if a successful postback already exists for this device, offer, and level
    $checkStmt = $db->prepare("SELECT id FROM postback_history WHERE device_id = ? AND offer_id = ? AND level = ? AND response_status >= 200 AND response_status < 300 AND id != ?");
    $checkStmt->execute([$deviceId, $offerId, $level, $recordId]);
    if ($checkStmt->fetch()) {
        $results[] = [
            'device_id' => $deviceId,
            'level' => $level,
            'coin' => $coin,
            'played_levels' => 'N/A',
            'expected' => 'N/A',
            'status' => 'Skip (Already Fired)',
            'badge' => 'badge-skip',
            'old_msg' => $oldMsg,
            'details' => 'A subsequent successful postback for this level already exists.'
        ];
        continue;
    }
    
    // Fetch campaign details and creation date
    $campStmt = $db->prepare("SELECT created_at FROM user_campaigns WHERE device_id = ? AND offer_id = ? LIMIT 1");
    $campStmt->execute([$deviceId, $offerId]);
    $campaign = $campStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$campaign) {
        $results[] = [
            'device_id' => $deviceId,
            'level' => $level,
            'coin' => $coin,
            'played_levels' => 'N/A',
            'expected' => 'N/A',
            'status' => 'Skip (No Campaign)',
            'badge' => 'badge-failed',
            'old_msg' => $oldMsg,
            'details' => 'No active/completed campaign attribution found for this device & offer.'
        ];
        continue;
    }
    
    $campaignCreatedAt = $campaign['created_at'];
    
    // Re-verify level history under the NEW RULE (excluding Level 1)
    // Check history starting from the latest Level 1 play time to resolve late/lazy campaign attribution.
    $histStmt = $db->prepare("SELECT COUNT(DISTINCT level) as played_levels FROM level_history WHERE device_id = ? AND level >= 2 AND level <= ? AND created_at >= (SELECT COALESCE(MAX(created_at), '1970-01-01') FROM level_history WHERE device_id = ? AND level = 1)");
    $histStmt->execute([$deviceId, $level, $deviceId]);
    $histData = $histStmt->fetch(PDO::FETCH_ASSOC);
    $playedLevels = $histData ? intval($histData['played_levels']) : 0;
    
    $expectedLevels = $level - 1;
    $isLegitimate = ($playedLevels >= $expectedLevels);
    
    if ($isLegitimate) {
        // Find matching settings for target level
        $matchedSettings = null;
        foreach ($postbackSettings as $settings) {
            if (intval($settings['target_level']) === $level) {
                $matchedSettings = $settings;
                break;
            }
        }
        
        // Fallback to first settings if no level-specific settings found
        if (!$matchedSettings && !empty($postbackSettings)) {
            $matchedSettings = $postbackSettings[0];
        }
        
        if ($matchedSettings) {
            $secretKey = $matchedSettings['secret_key'];
            $settingsEventId = $matchedSettings['event_id'] ?? '';
            $finalEventId = !empty($settingsEventId) ? $settingsEventId : $eventId;
            
            // Build the secure postback URL
            $postbackUrl = "https://app.rewardbro.in/cpa-postback?userId=" . urlencode($rewardbroUid) . 
                           "&appName=rewardbro" . 
                           "&offerId=" . urlencode($offerId) . 
                           "&secret=" . urlencode($secretKey) . 
                           "&eventId=" . urlencode($finalEventId);
            
            // Fire the postback via cURL
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
            
            // Update the original failed record to mark it as successful / fired
            $settingsCoin = $matchedSettings ? intval($matchedSettings['coin'] ?? 0) : $coin;
            $updateStmt = $db->prepare("UPDATE postback_history SET postback_url = ?, response_status = ?, response_body = ?, coin = ?, created_at = NOW() WHERE id = ?");
            $updateStmt->execute([$postbackUrl, $responseStatus, $responseBody, $settingsCoin, $recordId]);
            
            // Mark campaign status as completed if it was the max level
            $maxSettingsStmt = $db->query("SELECT MAX(target_level) as max_level FROM postback_settings");
            $maxLevelRow = $maxSettingsStmt->fetch();
            $maxLevel = $maxLevelRow ? intval($maxLevelRow['max_level']) : 0;
            
            if ($maxLevel > 0 && $level === $maxLevel && $responseStatus >= 200 && $responseStatus < 300) {
                $db->prepare("UPDATE user_campaigns SET status = 'completed' WHERE device_id = ? AND offer_id = ?")->execute([$deviceId, $offerId]);
            }
            
            $totalRetried++;
            $results[] = [
                'device_id' => $deviceId,
                'level' => $level,
                'coin' => $settingsCoin,
                'played_levels' => $playedLevels,
                'expected' => $expectedLevels,
                'status' => "SUCCESS (Retried $responseStatus) 🟢",
                'badge' => 'badge-success',
                'old_msg' => $oldMsg,
                'details' => "Successfully fired postback! Server response: " . htmlspecialchars($responseBody)
            ];
        } else {
            $results[] = [
                'device_id' => $deviceId,
                'level' => $level,
                'coin' => $coin,
                'played_levels' => $playedLevels,
                'expected' => $expectedLevels,
                'status' => 'No Secret Key',
                'badge' => 'badge-failed',
                'old_msg' => $oldMsg,
                'details' => 'Could not find postback settings secret key to construct postback URL.'
            ];
        }
    } else {
        $results[] = [
            'device_id' => $deviceId,
            'level' => $level,
            'coin' => $coin,
            'played_levels' => $playedLevels,
            'expected' => $expectedLevels,
            'status' => 'Still Incomplete 🔴',
            'badge' => 'badge-failed',
            'old_msg' => $oldMsg,
            'details' => "Progress check failed. Only played $playedLevels of $expectedLevels levels (excluding Level 1)."
        ];
    }
}

// 4. Output rendering
if ($isCli) {
    echo "=== POSTBACK HISTORY CLEANUP & RETRY REPORT ===\n";
    echo "Total cancelled postbacks scanned: $totalScanned\n";
    echo "Total postbacks successfully retried: $totalRetried\n\n";
    printf("%-20s | %-6s | %-10s | %-16s | %-20s | %s\n", "Device ID", "Level", "Coins", "Played/Expected", "Status", "Details");
    echo str_repeat("-", 130) . "\n";
    foreach ($results as $r) {
        printf("%-20s | %-6d | %-10d | %-16s | %-20s | %s\n", $r['device_id'], $r['level'], $r['coin'], $r['played_levels'] . '/' . $r['expected'], $r['status'], $r['details']);
    }
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Postback Retry Dashboard</title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg-primary: #0a0e17;
                --bg-secondary: #121826;
                --border-color: #232d45;
                --text-primary: #f1f5f9;
                --text-secondary: #94a3b8;
                --accent-blue: #3b82f6;
                --accent-emerald: #10b981;
                --accent-rose: #f43f5e;
                --accent-amber: #f59e0b;
            }
            body {
                font-family: 'Outfit', sans-serif;
                background-color: var(--bg-primary);
                color: var(--text-primary);
                margin: 0;
                padding: 40px 20px;
                display: flex;
                justify-content: center;
            }
            .container {
                width: 100%;
                max-width: 1200px;
                background: var(--bg-secondary);
                padding: 35px;
                border-radius: 16px;
                border: 1px solid var(--border-color);
                box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.5);
            }
            .header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-bottom: 1px solid var(--border-color);
                padding-bottom: 25px;
                margin-bottom: 30px;
            }
            h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 700;
                background: linear-gradient(90deg, #3b82f6, #60a5fa);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            .stats-container {
                display: flex;
                gap: 20px;
                margin-bottom: 30px;
            }
            .stat-box {
                flex: 1;
                background: rgba(255, 255, 255, 0.02);
                border: 1px solid var(--border-color);
                padding: 20px;
                border-radius: 12px;
                text-align: center;
            }
            .stat-value {
                font-size: 32px;
                font-weight: 700;
                color: var(--accent-blue);
                margin-bottom: 5px;
            }
            .stat-value.emerald {
                color: var(--accent-emerald);
            }
            .stat-label {
                font-size: 14px;
                color: var(--text-secondary);
                font-weight: 500;
            }
            table {
                width: 100%;
                border-collapse: separate;
                border-spacing: 0;
                margin-top: 15px;
                border-radius: 10px;
                overflow: hidden;
                border: 1px solid var(--border-color);
            }
            th, td {
                padding: 16px 20px;
                text-align: left;
                font-size: 14px;
                border-bottom: 1px solid var(--border-color);
            }
            th {
                background-color: rgba(255, 255, 255, 0.03);
                font-weight: 600;
                color: var(--text-primary);
                font-size: 13px;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }
            tr:last-child td {
                border-bottom: none;
            }
            tr:hover td {
                background-color: rgba(255, 255, 255, 0.01);
            }
            .badge {
                padding: 5px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 600;
                display: inline-block;
                text-transform: uppercase;
                letter-spacing: 0.03em;
            }
            .badge-success {
                background-color: rgba(16, 185, 129, 0.15);
                color: var(--accent-emerald);
                border: 1px solid rgba(16, 185, 129, 0.3);
            }
            .badge-failed {
                background-color: rgba(244, 63, 94, 0.15);
                color: var(--accent-rose);
                border: 1px solid rgba(244, 63, 94, 0.3);
            }
            .badge-skip {
                background-color: rgba(148, 163, 184, 0.15);
                color: var(--text-secondary);
                border: 1px solid rgba(148, 163, 184, 0.3);
            }
            code {
                background: rgba(255, 255, 255, 0.05);
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-family: 'Courier New', Courier, monospace;
                color: #f472b6;
            }
            .old-msg {
                font-size: 11px;
                color: var(--text-secondary);
                max-width: 300px;
                word-wrap: break-word;
                white-space: normal;
                display: block;
                margin-top: 5px;
                font-style: italic;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Postback Verification & Retry Console</h1>
                <div class="stat-label">
                    Mode: Secure Web Dashboard
                </div>
            </div>
            
            <div class="stats-container">
                <div class="stat-box">
                    <div class="stat-value"><?= $totalScanned ?></div>
                    <div class="stat-label">Total Cancelled Scanned</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value emerald"><?= $totalRetried ?></div>
                    <div class="stat-label">Successfully Retried & Fired</div>
                </div>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Device ID</th>
                        <th>Target Level</th>
                        <th>Coins</th>
                        <th>Played/Expected (Excl. L1)</th>
                        <th>Status</th>
                        <th>Old Error & Action Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: var(--text-secondary); padding: 50px; font-weight: 500;">
                                No cancelled postbacks found in history that match the retry criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($results as $r): ?>
                            <tr>
                                <td><code><?= $r['device_id'] ?></code></td>
                                <td><strong>Level <?= $r['level'] ?></strong></td>
                                <td><span style="font-weight: 600; color: var(--accent-amber);"><?= $r['coin'] ?> coins</span></td>
                                <td>
                                    <?php if ($r['played_levels'] !== 'N/A'): ?>
                                        <span style="font-weight: 600; color: <?= ($r['played_levels'] >= $r['expected']) ? 'var(--accent-emerald)' : 'var(--accent-rose)' ?>">
                                            <?= $r['played_levels'] ?>
                                        </span> / <?= $r['expected'] ?> levels
                                    <?php else: ?>
                                        <span class="text-null" style="color: var(--text-secondary);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $r['badge'] ?>"><?= $r['status'] ?></span>
                                </td>
                                <td style="font-size: 13px; line-height: 1.5;">
                                    <div style="font-weight: 500;"><?= $r['details'] ?></div>
                                    <span class="old-msg" title="Original Error Details">Original Error: <?= htmlspecialchars($r['old_msg']) ?></span>
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
