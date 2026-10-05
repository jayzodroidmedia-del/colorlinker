<?php
require_once __DIR__ . '/config.php';

// Authentication Pin Check
$pin = $_GET['pin'] ?? '';
if ($pin !== 'mint3') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied. Pass the correct ?pin= to access.");
}

try {
    $db = getDB();
    
    $searchUid = trim($_GET['uid'] ?? '');
    
    if (!empty($searchUid)) {
        // 1. Fetch campaigns for this user
        $stmtCampaigns = $db->prepare("SELECT device_id, rewardbro_uid, offer_id, event_id, referrer, status, created_at FROM user_campaigns WHERE rewardbro_uid = ? ORDER BY created_at DESC");
        $stmtCampaigns->execute([$searchUid]);
        $campaigns = $stmtCampaigns->fetchAll(PDO::FETCH_ASSOC);

        // Collect all related device IDs
        $deviceIds = array_column($campaigns, 'device_id');

        // 2. Fetch device keys for these device IDs or search by rewardbro_uid
        $devices = [];
        $logs = [];
        
        if (!empty($deviceIds)) {
            $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
            $stmtDevices = $db->prepare("SELECT device_id, rewardbro_uid, referrer, gid, is_registered, pkg_name, registration_ip, created_at FROM device_keys WHERE rewardbro_uid = ? OR device_id IN ($placeholders) ORDER BY id DESC");
            $stmtDevices->execute(array_merge([$searchUid], $deviceIds));
            $devices = $stmtDevices->fetchAll(PDO::FETCH_ASSOC);
            
            // Collect any additional device IDs found
            $deviceIds = array_unique(array_merge($deviceIds, array_column($devices, 'device_id')));
        } else {
            $stmtDevices = $db->prepare("SELECT device_id, rewardbro_uid, referrer, gid, is_registered, pkg_name, registration_ip, created_at FROM device_keys WHERE rewardbro_uid = ? ORDER BY id DESC");
            $stmtDevices->execute([$searchUid]);
            $devices = $stmtDevices->fetchAll(PDO::FETCH_ASSOC);
            $deviceIds = array_column($devices, 'device_id');
        }

        // 3. Fetch clicks
        $stmtClicks = $db->prepare("SELECT id, offer_id, rewardbro_uid, event_id, ip_address, referrer, status, created_at FROM campaign_clicks WHERE rewardbro_uid = ? ORDER BY id DESC");
        $stmtClicks->execute([$searchUid]);
        $clicks = $stmtClicks->fetchAll(PDO::FETCH_ASSOC);

        // 4. Fetch postbacks
        $stmtPostbacks = $db->prepare("SELECT id, device_id, rewardbro_uid, offer_id, level, postback_url, response_status, response_body, coin, created_at FROM postback_history WHERE rewardbro_uid = ? ORDER BY id DESC");
        $stmtPostbacks->execute([$searchUid]);
        $postbacks = $stmtPostbacks->fetchAll(PDO::FETCH_ASSOC);

        // 5. Fetch security logs for these device IDs
        if (!empty($deviceIds)) {
            $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
            $stmtLogs = $db->prepare("SELECT id, device_id, ip_address, event_type, details, created_at FROM security_logs WHERE device_id IN ($placeholders) ORDER BY id DESC LIMIT 50");
            $stmtLogs->execute($deviceIds);
            $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);
        }
    } else {
        // Fetch last 15 security logs
        $stmtLogs = $db->query("SELECT id, device_id, ip_address, event_type, details, created_at FROM security_logs ORDER BY id DESC LIMIT 15");
        $logs = $stmtLogs->fetchAll(PDO::FETCH_ASSOC);

        // Fetch last 10 device keys
        $stmtDevices = $db->query("SELECT device_id, rewardbro_uid, referrer, gid, is_registered, pkg_name, registration_ip, created_at FROM device_keys ORDER BY id DESC LIMIT 10");
        $devices = $stmtDevices->fetchAll(PDO::FETCH_ASSOC);

        // Fetch last 15 campaign clicks
        $stmtClicks = $db->query("SELECT id, offer_id, rewardbro_uid, event_id, ip_address, referrer, status, created_at FROM campaign_clicks ORDER BY id DESC LIMIT 15");
        $clicks = $stmtClicks->fetchAll(PDO::FETCH_ASSOC);

        // Fetch last 10 user campaigns
        $stmtCampaigns = $db->query("SELECT device_id, rewardbro_uid, offer_id, event_id, referrer, status, created_at FROM user_campaigns ORDER BY created_at DESC LIMIT 10");
        $campaigns = $stmtCampaigns->fetchAll(PDO::FETCH_ASSOC);

        // Fetch last 15 postback history records
        $stmtPostbacks = $db->query("SELECT id, device_id, rewardbro_uid, offer_id, level, postback_url, response_status, response_body, coin, created_at FROM postback_history ORDER BY id DESC LIMIT 15");
        $postbacks = $stmtPostbacks->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    exit("Database Query Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Mathmint Database Debugger</title>
    <style>
        body {
            background-color: #0d1117;
            color: #c9d1d9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        h1, h2 {
            color: #58a6ff;
            border-bottom: 1px solid #21262d;
            padding-bottom: 8px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
            background-color: #161b22;
            border: 1px solid #30363d;
            border-radius: 6px;
            overflow: hidden;
        }
        th, td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #30363d;
            font-size: 13px;
        }
        th {
            background-color: #21262d;
            color: #f0f6fc;
            font-weight: 600;
        }
        tr:hover {
            background-color: #1f242c;
        }
        .status-badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background-color: rgba(210, 153, 34, 0.15); color: #d29922; border: 1px solid rgba(210, 153, 34, 0.4); }
        .status-attributed { background-color: rgba(56, 139, 253, 0.15); color: #58a6ff; border: 1px solid rgba(56, 139, 253, 0.4); }
        .status-active { background-color: rgba(46, 160, 67, 0.15); color: #3fb950; border: 1px solid rgba(46, 160, 67, 0.4); }
        .text-null {
            color: #8b949e;
            font-style: italic;
        }
        .highlight {
            color: #3fb950;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Mathmint Database Debugging Panel</h1>
    
    <!-- Search Form -->
    <div style="background-color: #161b22; border: 1px solid #30363d; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
        <form method="GET" style="display: flex; gap: 10px; align-items: center;">
            <input type="hidden" name="pin" value="<?= htmlspecialchars($pin) ?>">
            <label for="uid" style="font-weight: bold; color: #58a6ff;">Search RewardBro UID:</label>
            <input type="text" id="uid" name="uid" value="<?= htmlspecialchars($searchUid ?? '') ?>" placeholder="e.g. sgQo4a3Hu5UGLOWEGp7qWcPrirs1" style="flex: 1; padding: 8px; background-color: #0d1117; border: 1px solid #30363d; border-radius: 4px; color: #c9d1d9;">
            <button type="submit" style="padding: 8px 16px; background-color: #21262d; border: 1px solid #30363d; border-radius: 4px; color: #c9d1d9; cursor: pointer; font-weight: bold;">Search</button>
            <?php if (!empty($searchUid)): ?>
                <a href="?pin=<?= htmlspecialchars($pin) ?>" style="padding: 8px 16px; background-color: #8b1c1c; border: 1px solid #30363d; border-radius: 4px; color: #f0f6fc; text-decoration: none; font-weight: bold;">Clear Search</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!empty($searchUid)): ?>
        <p style="color: #58a6ff; font-weight: bold; margin-bottom: 20px;">Showing records only for UID: <?= htmlspecialchars($searchUid) ?></p>
    <?php else: ?>
        <p>Use this page to check referrer and GID registration values. Security Pin is active.</p>
    <?php endif; ?>

    <!-- 1. Security Logs -->
    <h2>Latest Security Logs (Last 15)</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Device ID</th>
                <th>IP Address</th>
                <th>Event Type</th>
                <th>Details</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['id']) ?></td>
                    <td><?= htmlspecialchars($log['device_id']) ?></td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                    <td><span class="highlight"><?= htmlspecialchars($log['event_type']) ?></span></td>
                    <td><?= htmlspecialchars($log['details']) ?></td>
                    <td><?= htmlspecialchars($log['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?><tr><td colspan="6" class="text-null">No security logs recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <!-- 2. Device Keys -->
    <h2>Latest Registered Devices in device_keys (Last 10)</h2>
    <table>
        <thead>
            <tr>
                <th>Device ID</th>
                <th>RewardBro UID</th>
                <th>Referrer Code</th>
                <th>Google Ad ID (GID)</th>
                <th>Registered</th>
                <th>Package Name</th>
                <th>Registration IP</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($devices as $dev): ?>
                <tr>
                    <td><?= htmlspecialchars($dev['device_id']) ?></td>
                    <td><?= htmlspecialchars($dev['rewardbro_uid'] ?? '') ?: '<span class="text-null">NULL</span>' ?></td>
                    <td><?= htmlspecialchars($dev['referrer'] ?? '') ? '<span class="highlight">'.htmlspecialchars($dev['referrer']).'</span>' : '<span class="text-null">NULL (No Match)</span>' ?></td>
                    <td><?= htmlspecialchars($dev['gid'] ?? '') ?: '<span class="text-null">NULL</span>' ?></td>
                    <td><?= $dev['is_registered'] ? 'Yes' : 'No' ?></td>
                    <td><?= htmlspecialchars($dev['pkg_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($dev['registration_ip'] ?? '') ?></td>
                    <td><?= htmlspecialchars($dev['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($devices)): ?><tr><td colspan="8" class="text-null">No devices registered.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <!-- 3. Campaign Clicks -->
    <h2>Campaign Clicks Logged from Links (Last 15)</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Offer ID</th>
                <th>RewardBro UID</th>
                <th>Event ID</th>
                <th>IP Address</th>
                <th>Referrer Code</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clicks as $click): ?>
                <tr>
                    <td><?= htmlspecialchars($click['id']) ?></td>
                    <td><?= htmlspecialchars($click['offer_id']) ?></td>
                    <td><?= htmlspecialchars($click['rewardbro_uid']) ?></td>
                    <td><?= htmlspecialchars($click['event_id']) ?></td>
                    <td><?= htmlspecialchars($click['ip_address']) ?></td>
                    <td><span class="highlight"><?= htmlspecialchars($click['referrer'] ?? '') ?></span></td>
                    <td>
                        <span class="status-badge status-<?= htmlspecialchars($click['status']) ?>">
                            <?= htmlspecialchars($click['status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($click['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($clicks)): ?><tr><td colspan="8" class="text-null">No campaign clicks logged.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <!-- 4. User Campaigns -->
    <h2>Attributed User Campaigns (Last 10)</h2>
    <table>
        <thead>
            <tr>
                <th>Device ID</th>
                <th>RewardBro UID</th>
                <th>Offer ID</th>
                <th>Event ID</th>
                <th>Referrer</th>
                <th>Status</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($campaigns as $camp): ?>
                <tr>
                    <td><?= htmlspecialchars($camp['device_id']) ?></td>
                    <td><?= htmlspecialchars($camp['rewardbro_uid']) ?></td>
                    <td><?= htmlspecialchars($camp['offer_id']) ?></td>
                    <td><?= htmlspecialchars($camp['event_id']) ?></td>
                    <td><?= htmlspecialchars($camp['referrer'] ?? '') ?: '<span class="text-null">NULL (No Match)</span>' ?></td>
                    <td><span class="status-badge status-active"><?= htmlspecialchars($camp['status']) ?></span></td>
                    <td><?= htmlspecialchars($camp['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($campaigns)): ?><tr><td colspan="7" class="text-null">No attributed campaigns yet.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <!-- 5. Postback History -->
    <h2>Latest Postback History (Last 15)</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Device ID</th>
                <th>RewardBro UID</th>
                <th>Offer ID</th>
                <th>Target Level</th>
                <th>Coins Sent</th>
                <th>Response Status</th>
                <th>Response Body</th>
                <th>Created At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($postbacks as $pb): ?>
                <tr>
                    <td><?= htmlspecialchars($pb['id']) ?></td>
                    <td><code><?= htmlspecialchars($pb['device_id']) ?></code></td>
                    <td><?= htmlspecialchars($pb['rewardbro_uid']) ?></td>
                    <td><?= htmlspecialchars($pb['offer_id']) ?></td>
                    <td><strong>Level <?= htmlspecialchars($pb['level']) ?></strong></td>
                    <td><span style="color: #f59e0b; font-weight: bold;"><?= htmlspecialchars($pb['coin'] ?? 0) ?> coins</span></td>
                    <td>
                        <span class="status-badge <?= intval($pb['response_status']) >= 200 && intval($pb['response_status']) < 300 ? 'status-active' : 'status-pending' ?>">
                            <?= htmlspecialchars($pb['response_status']) ?>
                        </span>
                    </td>
                    <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($pb['response_body']) ?>">
                        <?= htmlspecialchars($pb['response_body']) ?>
                    </td>
                    <td><?= htmlspecialchars($pb['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($postbacks)): ?><tr><td colspan="9" class="text-null">No postback history recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
