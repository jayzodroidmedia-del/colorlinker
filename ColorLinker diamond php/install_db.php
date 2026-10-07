<?php
/**
 * Pipecraze - Dedicated Database Schema Installer & Migration Script
 * Run this file whenever you set up a new database or add new database features/tables.
 * Access via Browser: http://your-domain.com/install_db.php
 * Access via CLI: php install_db.php
 */

require_once __DIR__ . '/config.php';

// Set execution time limit
@set_time_limit(120);

$results = [];
$errors = [];

function logAction($title, $success = true, $details = '') {
    global $results, $errors;
    $entry = ['title' => $title, 'success' => $success, 'details' => $details];
    $results[] = $entry;
    if (!$success) {
        $errors[] = "$title: $details";
    }
}

try {
    // 1. Establish Database Connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("SET time_zone = '+00:00'");
    logAction("Database Connection", true, "Connected to MySQL host '" . DB_HOST . "' database '" . DB_NAME . "'");

    // 2. Core Tables
    $tables = [
        "users" => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(255) NOT NULL UNIQUE,
            rewardbro_uid VARCHAR(255) NULL,
            current_level INT NOT NULL DEFAULT 1,
            is_banned TINYINT(1) DEFAULT 0,
            ip_address VARCHAR(45) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_u_device (device_id),
            INDEX idx_u_uid (rewardbro_uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "device_keys" => "CREATE TABLE IF NOT EXISTS device_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(255) NOT NULL UNIQUE,
            client_public_key TEXT NOT NULL,
            server_private_key TEXT NOT NULL,
            shared_secret VARCHAR(255) NOT NULL,
            rewardbro_uid VARCHAR(255) NULL,
            gid VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_dk_device (device_id),
            INDEX idx_dk_uid (rewardbro_uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "user_campaigns" => "CREATE TABLE IF NOT EXISTS user_campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(255) NOT NULL UNIQUE,
            rewardbro_uid VARCHAR(255) NOT NULL,
            offer_id VARCHAR(255) NOT NULL,
            event_id VARCHAR(255) NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_uc_device (device_id),
            INDEX idx_uc_uid (rewardbro_uid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "campaign_clicks" => "CREATE TABLE IF NOT EXISTS campaign_clicks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            rewardbro_uid VARCHAR(255) NOT NULL,
            offer_id VARCHAR(255) NOT NULL,
            gaid VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cc_uid (rewardbro_uid),
            INDEX idx_cc_gaid (gaid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "postback_settings" => "CREATE TABLE IF NOT EXISTS postback_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            target_level INT NOT NULL UNIQUE,
            event_id VARCHAR(255) NOT NULL,
            secret_key VARCHAR(255) NOT NULL,
            coin INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "postback_history" => "CREATE TABLE IF NOT EXISTS postback_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(255) NOT NULL,
            rewardbro_uid VARCHAR(255) NOT NULL,
            offer_id VARCHAR(255) NOT NULL,
            event_id VARCHAR(255) NOT NULL,
            level INT NOT NULL,
            postback_url TEXT NOT NULL,
            response_status INT NOT NULL,
            response_body TEXT NULL,
            coin INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ph_device (device_id),
            INDEX idx_ph_level (level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "security_logs" => "CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(255) NULL,
            ip_address VARCHAR(45) NOT NULL,
            event_type VARCHAR(100) NOT NULL,
            details TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sl_device (device_id),
            INDEX idx_sl_type (event_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "level_history" => "CREATE TABLE IF NOT EXISTS level_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(255) NOT NULL,
            level INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_device (device_id),
            INDEX idx_level (level),
            INDEX idx_dev_created (device_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "level_bypass_logs" => "CREATE TABLE IF NOT EXISTS level_bypass_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(255) NOT NULL,
            attempted_level INT NOT NULL,
            current_db_level INT NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_device_bypass (device_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "app_settings" => "CREATE TABLE IF NOT EXISTS app_settings (
            id INT PRIMARY KEY,
            force_update TINYINT(1) DEFAULT 0,
            latest_version_code INT DEFAULT 1,
            update_status VARCHAR(50) DEFAULT 'none',
            rewards_enabled TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "reward_settings" => "CREATE TABLE IF NOT EXISTS reward_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            required_level INT NOT NULL UNIQUE,
            reward_amount INT NOT NULL,
            message VARCHAR(255) NULL,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "rewards" => "CREATE TABLE IF NOT EXISTS rewards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(255) NOT NULL,
            reward_setting_id INT NOT NULL,
            reward_amount INT NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            account_details VARCHAR(255) NOT NULL,
            voucher_code VARCHAR(255) NULL DEFAULT NULL,
            admin_notes VARCHAR(255) NULL DEFAULT NULL,
            status ENUM('pending', 'approved', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_rwd_device (device_id),
            INDEX idx_rwd_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "app_banners" => "CREATE TABLE IF NOT EXISTS app_banners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL DEFAULT '',
            description VARCHAR(255) NOT NULL DEFAULT '',
            image_url TEXT NOT NULL,
            action_type VARCHAR(50) NOT NULL DEFAULT 'tester_program',
            action_value TEXT NULL,
            button_text VARCHAR(50) NOT NULL DEFAULT 'OPEN >',
            sort_order INT NOT NULL DEFAULT 0,
            status TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "tester_settings" => "CREATE TABLE IF NOT EXISTS tester_settings (
            id INT PRIMARY KEY,
            instant_approval TINYINT(1) NOT NULL DEFAULT 1,
            total_days INT NOT NULL DEFAULT 7,
            reward_amount INT NOT NULL DEFAULT 150,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            badge_text VARCHAR(255) NOT NULL DEFAULT 'EARN ₹150 GUARANTEED',
            badge_color VARCHAR(50) NOT NULL DEFAULT '#06D6A0',
            title VARCHAR(255) NOT NULL DEFAULT 'Join the 7-Day Testing Team',
            description TEXT NULL,
            perk1_title VARCHAR(100) NOT NULL DEFAULT '10 Levels/Day',
            perk1_subtitle VARCHAR(100) NOT NULL DEFAULT 'Daily Target',
            perk2_title VARCHAR(100) NOT NULL DEFAULT 'Bug Reports',
            perk2_subtitle VARCHAR(100) NOT NULL DEFAULT 'Quick Review',
            perk3_title VARCHAR(100) NOT NULL DEFAULT 'Instant Cash',
            perk3_subtitle VARCHAR(100) NOT NULL DEFAULT '₹150 Reward',
            ticker_enabled TINYINT(1) NOT NULL DEFAULT 1,
            ticker_show_home TINYINT(1) NOT NULL DEFAULT 1,
            ticker_show_register TINYINT(1) NOT NULL DEFAULT 1,
            ticker_show_dashboard TINYINT(1) NOT NULL DEFAULT 1,
            ticker_speed INT NOT NULL DEFAULT 25,
            ticker_custom_items TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "tester_day_configs" => "CREATE TABLE IF NOT EXISTS tester_day_configs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            day_number INT NOT NULL UNIQUE,
            required_levels INT NOT NULL DEFAULT 10,
            title VARCHAR(255) NOT NULL DEFAULT '',
            instructions TEXT NULL,
            status TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "tester_users" => "CREATE TABLE IF NOT EXISTS tester_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            device_id VARCHAR(255) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            mobile VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL,
            status ENUM('pending', 'active', 'rejected', 'completed') NOT NULL DEFAULT 'active',
            is_finished TINYINT(1) NOT NULL DEFAULT 0,
            current_day INT NOT NULL DEFAULT 1,
            current_cycle INT NOT NULL DEFAULT 1,
            registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            INDEX idx_tester_device (device_id),
            INDEX idx_tester_status (status),
            INDEX idx_tester_cycle (current_cycle)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "tester_daily_progress" => "CREATE TABLE IF NOT EXISTS tester_daily_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tester_user_id INT NOT NULL,
            device_id VARCHAR(255) NOT NULL,
            cycle INT NOT NULL DEFAULT 1,
            day_number INT NOT NULL,
            target_levels INT NOT NULL DEFAULT 10,
            levels_completed INT NOT NULL DEFAULT 0,
            feedback_text TEXT NULL,
            status ENUM('in_progress', 'completed', 'failed') NOT NULL DEFAULT 'in_progress',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            UNIQUE KEY uk_tester_day (device_id, cycle, day_number),
            INDEX idx_prog_device (device_id),
            INDEX idx_prog_cycle_status (device_id, cycle, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "tester_reward_claims" => "CREATE TABLE IF NOT EXISTS tester_reward_claims (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tester_user_id INT NOT NULL,
            device_id VARCHAR(255) NOT NULL,
            cycle INT NOT NULL DEFAULT 1,
            amount INT NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            account_details VARCHAR(255) NOT NULL,
            voucher_code VARCHAR(255) NULL DEFAULT NULL,
            admin_notes VARCHAR(255) NULL DEFAULT NULL,
            status ENUM('pending', 'sent', 'rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_claim_device (device_id),
            INDEX idx_claim_status (status),
            INDEX idx_claim_cycle (cycle)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "tester_payout_methods" => "CREATE TABLE IF NOT EXISTS tester_payout_methods (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            input_placeholder VARCHAR(255) NOT NULL DEFAULT '',
            status TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "admin_users" => "CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'admin',
            last_login TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "ad_controls" => "CREATE TABLE IF NOT EXISTS ad_controls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            screen_key VARCHAR(100) NOT NULL UNIQUE,
            screen_label VARCHAR(255) NOT NULL,
            ad_type VARCHAR(50) NOT NULL DEFAULT 'none',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",

        "push_notifications" => "CREATE TABLE IF NOT EXISTS push_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            image_url TEXT NULL,
            action_type VARCHAR(50) NOT NULL DEFAULT 'open_app',
            action_value TEXT NULL,
            button_text VARCHAR(100) NULL,
            onesignal_id VARCHAR(100) NULL,
            recipients_count INT DEFAULT 0,
            status VARCHAR(50) DEFAULT 'sent',
            response_raw TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pn_status (status),
            INDEX idx_pn_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
    ];

    foreach ($tables as $tName => $tSql) {
        $pdo->exec($tSql);
        logAction("Table: $tName", true, "Verified / Created table successfully");
    }

    // 3. Perform Column Migrations (ALTER TABLE if missing)
    $columnMigrations = [
        ['users', 'is_banned', "TINYINT(1) DEFAULT 0 AFTER current_level"],
        ['postback_settings', 'coin', "INT DEFAULT 0"],
        ['postback_history', 'coin', "INT DEFAULT 0 AFTER response_body"],
        ['app_settings', 'rewards_enabled', "TINYINT(1) DEFAULT 1 AFTER update_status"],
        ['app_settings', 'topon_app_id', "VARCHAR(100) NOT NULL DEFAULT 'h6a6dcd470d298'"],
        ['app_settings', 'topon_app_key', "VARCHAR(100) NOT NULL DEFAULT 'adc50c45a5db3969578a75b7843f78507'"],
        ['app_settings', 'topon_splash_id', "VARCHAR(100) NOT NULL DEFAULT 'b6a6dcd4790001'"],
        ['app_settings', 'topon_interstitial_id', "VARCHAR(100) NOT NULL DEFAULT 'b6a6dcd479a322'"],
        ['app_settings', 'topon_rewarded_id', "VARCHAR(100) NOT NULL DEFAULT 'b6a6dcd479a999'"],
        ['app_settings', 'topon_native_id', "VARCHAR(100) NOT NULL DEFAULT 'b6a6dcd479b111'"],
        ['app_settings', 'onesignal_app_id', "VARCHAR(255) NOT NULL DEFAULT ''"],
        ['app_settings', 'onesignal_rest_api_key', "TEXT NULL"],
        ['app_banners', 'description', "VARCHAR(255) NOT NULL DEFAULT '' AFTER title"],
        ['app_banners', 'button_text', "VARCHAR(50) NOT NULL DEFAULT 'OPEN >' AFTER action_value"],
        ['tester_users', 'is_finished', "TINYINT(1) NOT NULL DEFAULT 0"],
        ['tester_reward_claims', 'voucher_code', "VARCHAR(255) NULL DEFAULT NULL"],
        ['tester_reward_claims', 'admin_notes', "VARCHAR(255) NULL DEFAULT NULL"],
        ['rewards', 'voucher_code', "VARCHAR(255) NULL DEFAULT NULL"],
        ['rewards', 'admin_notes', "VARCHAR(255) NULL DEFAULT NULL"]
    ];

    foreach ($columnMigrations as $m) {
        list($tbl, $col, $def) = $m;
        try {
            $cCheck = $pdo->query("SHOW COLUMNS FROM `$tbl` LIKE '$col'");
            if ($cCheck && !$cCheck->fetch()) {
                $pdo->exec("ALTER TABLE `$tbl` ADD COLUMN `$col` $def");
                logAction("Migration: $tbl.$col", true, "Added missing column");
            } else {
                // Ensure onesignal_rest_api_key is expanded to TEXT
                if ($tbl === 'app_settings' && $col === 'onesignal_rest_api_key') {
                    $pdo->exec("ALTER TABLE `app_settings` MODIFY COLUMN `onesignal_rest_api_key` TEXT NULL");
                }
                if ($tbl === 'app_settings' && $col === 'onesignal_app_id') {
                    $pdo->exec("ALTER TABLE `app_settings` MODIFY COLUMN `onesignal_app_id` VARCHAR(255) NOT NULL DEFAULT ''");
                }
                logAction("Column: $tbl.$col", true, "Column verified & updated");
            }
        } catch (Exception $e) {
            logAction("Migration: $tbl.$col", false, $e->getMessage());
        }
    }

    // 4. Create Performance Indexes for ultra-fast queries
    $indexMigrations = [
        ['tester_daily_progress', 'idx_prog_cycle_status', "CREATE INDEX idx_prog_cycle_status ON tester_daily_progress (device_id, cycle, status)"],
        ['tester_reward_claims', 'idx_claim_status_cycle', "CREATE INDEX idx_claim_status_cycle ON tester_reward_claims (status, cycle)"],
        ['level_history', 'idx_dev_created', "CREATE INDEX idx_dev_created ON level_history (device_id, created_at)"]
    ];

    foreach ($indexMigrations as $idx) {
        list($tbl, $idxName, $sql) = $idx;
        try {
            $chk = $pdo->query("SHOW INDEX FROM `$tbl` WHERE Key_name = '$idxName'");
            if ($chk && !$chk->fetch()) {
                $pdo->exec($sql);
                logAction("Index: $tbl.$idxName", true, "Created speed optimization index");
            } else {
                logAction("Index: $tbl.$idxName", true, "Index already active");
            }
        } catch (Exception $e) {
            // Index might already exist or ignore
            logAction("Index: $tbl.$idxName", true, "Index checked (" . $e->getMessage() . ")");
        }
    }

    // 5. Seed Default Data

    // App Settings (ID 1)
    $stmt = $pdo->query("SELECT id FROM app_settings WHERE id = 1 LIMIT 1");
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO app_settings (id, force_update, latest_version_code, update_status, rewards_enabled) VALUES (1, 0, 1, 'none', 1)");
        logAction("Seed: app_settings", true, "Created default app settings record");
    }

    // Tester Settings (ID 1)
    $stmt = $pdo->query("SELECT id FROM tester_settings WHERE id = 1 LIMIT 1");
    if (!$stmt->fetch()) {
        $pdo->exec("INSERT INTO tester_settings (id, instant_approval, total_days, reward_amount, is_active, badge_text, badge_color, title, description, perk1_title, perk1_subtitle, perk2_title, perk2_subtitle, perk3_title, perk3_subtitle) 
                   VALUES (1, 1, 7, 150, 1, 'EARN ₹150 GUARANTEED', '#06D6A0', 'Join the 7-Day Testing Team', 'Play 10 puzzle levels every day for 7 days, submit short daily bug reviews, and claim direct UPI/Paytm payout on Day 7!', '10 Levels/Day', 'Daily Target', 'Bug Reports', 'Quick Review', 'Instant Cash', '₹150 Reward')");
        logAction("Seed: tester_settings", true, "Created default tester settings record");
    }

    // Tester 7 Days Configuration
    $cntDays = $pdo->query("SELECT COUNT(*) FROM tester_day_configs")->fetchColumn();
    if (intval($cntDays) === 0) {
        $pdo->exec("INSERT INTO tester_day_configs (day_number, required_levels, title, instructions, status) VALUES
            (1, 10, 'Day 1: Beginners Puzzle Test', 'Complete 10 levels and report any initial bugs.', 1),
            (2, 10, 'Day 2: Pipe Puzzle Mechanics Test', 'Complete 10 levels testing speed and touch responsiveness.', 1),
            (3, 10, 'Day 3: Combo Release Test', 'Complete 10 levels testing obstacle clearing.', 1),
            (4, 10, 'Day 4: Grid Untangle Test', 'Complete 10 levels testing large mazes.', 1),
            (5, 10, 'Day 5: Performance & Ad Flow', 'Complete 10 levels testing smooth transitions.', 1),
            (6, 10, 'Day 6: Advanced Puzzle Master', 'Complete 10 levels with minimal hints.', 1),
            (7, 10, 'Day 7: Final Grandmaster Test', 'Complete final 10 levels and submit your overall review!', 1)
        ");
        logAction("Seed: tester_day_configs", true, "Populated 7 standard test days");
    }

    // Tester Payout Methods
    $cntMethods = $pdo->query("SELECT COUNT(*) FROM tester_payout_methods")->fetchColumn();
    if (intval($cntMethods) === 0) {
        $pdo->exec("INSERT INTO tester_payout_methods (name, input_placeholder, status, sort_order) VALUES
            ('UPI', 'Enter UPI ID (e.g. name@okhdfcbank)', 1, 1),
            ('Paytm', 'Enter 10-digit Paytm Wallet/Mobile Number', 1, 2),
            ('Google Play', 'Enter Gmail Address for Redeem Code', 1, 3),
            ('Amazon Pay', 'Enter Amazon registered Mobile or Email', 1, 4)");
        logAction("Seed: tester_payout_methods", true, "Populated default payout options");
    }

    // Default Admin User (admin / admin123)
    $cntAdmin = $pdo->query("SELECT COUNT(*) FROM admin_users")->fetchColumn();
    if (intval($cntAdmin) === 0) {
        $defaultHash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmtInitAdmin = $pdo->prepare("INSERT INTO admin_users (username, password_hash, role) VALUES (?, ?, 'admin')");
        $stmtInitAdmin->execute(['admin', $defaultHash]);
        logAction("Seed: admin_users", true, "Created default admin user (Username: admin | Password: admin123)");
    }

    // App Banners Seed
    $cntBanners = $pdo->query("SELECT COUNT(*) FROM app_banners")->fetchColumn();
    if (intval($cntBanners) === 0) {
        $pdo->exec("INSERT INTO app_banners (title, description, image_url, action_type, action_value, button_text, sort_order, status) VALUES
            ('Join 7-Day Testing Team 🏹', 'Play 10 Levels/Day & Earn ₹150 Guaranteed Direct Payout!', 'https://images.unsplash.com/photo-1511512578047-dfb367046420?w=800&auto=format&fit=crop&q=80', 'tester_program', '', 'START NOW >', 1, 1),
            ('Instant Cash & Voucher Rewards 🎁', 'Complete Milestone Levels & Claim UPI / Play Code Rewards!', 'https://images.unsplash.com/photo-1579621970563-ebec7560ff3e?w=800&auto=format&fit=crop&q=80', 'reward_center', '', 'CLAIM ₹150 >', 2, 1)
        ");
        logAction("Seed: app_banners", true, "Populated default home screen slider banners");
    }

    // Seed Ad Controls (11 standard triggers/touchpoints)
    $defaultAdControls = [
        ['splash_open', 'Splash Screen (App Open)', 'none'],
        ['home_tap_to_play', 'Home Screen - Tap to Play', 'none'],
        ['game_reset', 'Game Screen - Reset Button', 'none'],
        ['game_hint', 'Game Screen - Hint Button', 'none'],
        ['game_over_resume', 'Game Over - Resume Button', 'none'],
        ['next_level', 'Regular Mode - Next Level', 'none'],
        ['tester_form_submit', 'Tester Screen - Registration Submit', 'none'],
        ['tester_refresh', 'Tester Journey - Refresh Button', 'none'],
        ['tester_next_level', 'Tester Mode - Next Level', 'none'],
        ['tester_rejoin_cycle', 'Tester Circle - Rejoin / Next Cycle', 'none'],
        ['tester_end_program', 'End Tester Program - Yes, End', 'none'],
        ['tester_review_submit', 'Tester Mode - Submit QA Report & Unlock Day', 'none'],
        ['tester_dashboard_native', 'Tester Journey - In-Feed Native Ad (Between Day 1 & Day 2)', 'none'],
        ['tester_claim_reward', 'Tester Reward - Unwrap Mystery Box', 'none']
    ];

    $stmtAdCheck = $pdo->prepare("SELECT COUNT(*) FROM ad_controls WHERE screen_key = ?");
    $stmtAdInsert = $pdo->prepare("INSERT INTO ad_controls (screen_key, screen_label, ad_type) VALUES (?, ?, ?)");

    foreach ($defaultAdControls as $adControl) {
        $stmtAdCheck->execute([$adControl[0]]);
        if (intval($stmtAdCheck->fetchColumn()) === 0) {
            $stmtAdInsert->execute($adControl);
            logAction("Seed: ad_control." . $adControl[0], true, "Added default ad control trigger");
        }
    }

} catch (Exception $e) {
    logAction("Critical Database Error", false, $e->getMessage());
}

// Check if running from CLI
if (php_sapi_name() === 'cli') {
    echo "====================================================\n";
    echo " Pipecraze - Database Installer & Schema Migration  \n";
    echo "====================================================\n";
    foreach ($results as $r) {
        $status = $r['success'] ? '[OK]' : '[ERROR]';
        echo sprintf("%-8s %-30s : %s\n", $status, $r['title'], $r['details']);
    }
    echo "====================================================\n";
    if (empty($errors)) {
        echo "SUCCESS: Database schema is completely up to date!\n";
    } else {
        echo "FAILED: " . count($errors) . " error(s) occurred.\n";
    }
    exit(empty($errors) ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pipecraze • Database Migration & Setup</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #090d16;
            --bg-card: #131d33;
            --border: #1e293b;
            --primary: #6366f1;
            --success: #10b981;
            --danger: #ef4444;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body {
            background: radial-gradient(circle at top, #1e1b4b 0%, #090d16 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
            color: var(--text-main);
        }
        .setup-container {
            width: 100%;
            max-width: 760px;
            background: rgba(19, 29, 51, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
        }
        .header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        .icon {
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, var(--primary), #a855f7);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
        }
        .title h1 { font-size: 22px; font-weight: 800; color: #fff; }
        .title p { font-size: 13px; color: var(--text-muted); margin-top: 2px; }
        .status-badge {
            margin-left: auto;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-success { background: rgba(16, 185, 129, 0.15); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-danger { background: rgba(239, 68, 68, 0.15); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.3); }
        .log-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            max-height: 420px;
            overflow-y: auto;
            padding-right: 6px;
            margin-bottom: 24px;
        }
        .log-item {
            background: #0b1220;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
        }
        .log-item.error { border-color: rgba(239, 68, 68, 0.5); background: rgba(239, 68, 68, 0.05); }
        .log-name { font-weight: 600; color: #fff; display: flex; align-items: center; gap: 8px; }
        .log-detail { font-family: 'JetBrains Mono', monospace; font-size: 11px; color: var(--text-muted); }
        .actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-primary { background: linear-gradient(135deg, var(--primary), #4f46e5); color: #fff; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-secondary { background: #1e293b; color: #e2e8f0; }
        .btn-secondary:hover { background: #334155; }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="header">
            <div class="icon">🚀</div>
            <div class="title">
                <h1>Database Migration & Setup</h1>
                <p>Pipecraze Studio Schema Installer</p>
            </div>
            <?php if (empty($errors)): ?>
                <div class="status-badge badge-success">✓ Up To Date</div>
            <?php else: ?>
                <div class="status-badge badge-danger">⚠️ <?= count($errors) ?> Errors</div>
            <?php endif; ?>
        </div>

        <div class="log-list">
            <?php foreach ($results as $res): ?>
                <div class="log-item <?= $res['success'] ? '' : 'error' ?>">
                    <div class="log-name">
                        <span><?= $res['success'] ? '✅' : '❌' ?></span>
                        <span><?= htmlspecialchars($res['title']) ?></span>
                    </div>
                    <div class="log-detail"><?= htmlspecialchars($res['details']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="actions">
            <a href="install_db.php" class="btn btn-secondary">🔄 Re-run Migration</a>
            <a href="admin.php" class="btn btn-primary">🔐 Go to Admin Panel →</a>
        </div>
    </div>
</body>
</html>
