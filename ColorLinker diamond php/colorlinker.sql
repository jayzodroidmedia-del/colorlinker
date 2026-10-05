-- ========================================================
-- ColorLinker Complete Production Database SQL Dump
-- Database: colorlinker
-- Package: com.colorlinker.puzzle
-- ========================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- 1. Table structure for `admin_users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'admin',
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Admin User: admin / admin123
INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `role`, `created_at`) 
VALUES (1, 'admin', '$2y$10$e0MYzXyjpJS7Pd0RVvHwHeFkV4E4Ww6Q5EwX3d0gqP0uV0Nl9Gv1q', 'admin', NOW())
ON DUPLICATE KEY UPDATE username=username;

-- --------------------------------------------------------
-- 2. Table structure for `ad_controls`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ad_controls` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `screen_key` VARCHAR(100) NOT NULL UNIQUE,
  `screen_label` VARCHAR(255) NOT NULL,
  `ad_type` VARCHAR(50) NOT NULL DEFAULT 'none',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `ad_controls` (`id`, `screen_key`, `screen_label`, `ad_type`) VALUES
(1, 'MainActivity', 'App Home Screen Banner/Native', 'native'),
(2, 'quiz', 'Game Play Screen (Legacy Quiz)', 'native'),
(3, 'gameplay_native', 'Active Puzzle Play Board Native Ad', 'native'),
(4, 'level_completed_interstitial', 'Level Completed Success Interstitial', 'interstitial'),
(5, 'level_restart_interstitial', 'Level Failed / Restart Interstitial', 'interstitial'),
(6, 'tester_day_completed_interstitial', 'Tester Day Mission Finished Interstitial', 'interstitial'),
(7, 'tester_reward_claim_interstitial', 'Tester Payout Claim Interstitial', 'interstitial'),
(8, 'reward_center_interstitial', 'Milestone Reward Screen Interstitial', 'interstitial'),
(9, 'app_exit_interstitial', 'App Exit / Back Confirmation Interstitial', 'interstitial'),
(10, 'free_hint_rewarded', 'In-Game Free Hint Rewarded Video', 'rewarded'),
(11, 'free_lives_rewarded', 'In-Game Refill Hearts / Lives Rewarded Video', 'rewarded')
ON DUPLICATE KEY UPDATE screen_key=screen_key;

-- --------------------------------------------------------
-- 3. Table structure for `app_settings`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_settings` (
  `id` INT PRIMARY KEY,
  `topon_app_id` VARCHAR(100) NOT NULL DEFAULT 'h6abf939ee7d84',
  `topon_app_key` VARCHAR(100) NOT NULL DEFAULT 'a174dbc40be220647879d701c773493f7',
  `topon_splash_id` VARCHAR(100) NOT NULL DEFAULT 'b6a6dcd4790001',
  `topon_interstitial_id` VARCHAR(100) NOT NULL DEFAULT 'n6abf9442e8673',
  `topon_rewarded_id` VARCHAR(100) NOT NULL DEFAULT 'n6abf9427b3f3f',
  `topon_native_id` VARCHAR(100) NOT NULL DEFAULT 'n6abf93fa45aa7',
  `interstitial_id` VARCHAR(255) DEFAULT 'n6abf9442e8673',
  `native_id` VARCHAR(255) DEFAULT 'n6abf93fa45aa7',
  `rewarded_id` VARCHAR(255) DEFAULT 'n6abf9427b3f3f',
  `onesignal_app_id` VARCHAR(255) NOT NULL DEFAULT '',
  `onesignal_rest_api_key` TEXT NULL,
  `native_enabled` TINYINT(1) DEFAULT 1,
  `daily_refresh` TINYINT(1) DEFAULT 0,
  `force_update` TINYINT(1) DEFAULT 0,
  `latest_version_code` INT DEFAULT 1,
  `update_url` VARCHAR(255) DEFAULT 'https://play.google.com/store/apps/details?id=com.colorlinker.puzzle',
  `update_version` VARCHAR(50) DEFAULT '1.0',
  `update_status` TINYINT(1) DEFAULT 0,
  `rewards_enabled` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_settings` (`id`, `topon_app_id`, `topon_app_key`, `topon_splash_id`, `topon_interstitial_id`, `topon_rewarded_id`, `topon_native_id`, `interstitial_id`, `native_id`, `rewarded_id`, `native_enabled`, `daily_refresh`, `update_url`, `update_version`, `update_status`, `rewards_enabled`) 
VALUES (1, 'h6abf939ee7d84', 'a174dbc40be220647879d701c773493f7', 'b6a6dcd4790001', 'n6abf9442e8673', 'n6abf9427b3f3f', 'n6abf93fa45aa7', 'n6abf9442e8673', 'n6abf93fa45aa7', 'n6abf9427b3f3f', 1, 1, 'https://play.google.com/store/apps/details?id=com.colorlinker.puzzle', '1.0', 0, 1)
ON DUPLICATE KEY UPDATE id=id;

-- --------------------------------------------------------
-- 4. Table structure for `users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` VARCHAR(255) NOT NULL UNIQUE,
  `current_level` INT DEFAULT 1,
  `is_banned` TINYINT(1) DEFAULT 0,
  `rewardbro_uid` VARCHAR(255) DEFAULT NULL,
  `gid` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_users_device` (`device_id`),
  INDEX `idx_users_rewardbro` (`rewardbro_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. Table structure for `device_keys`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `device_keys` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` VARCHAR(255) NOT NULL UNIQUE,
  `rsa1_public_key` TEXT NOT NULL,
  `rsa1_private_key` TEXT NOT NULL,
  `rsa2_public_key` TEXT NOT NULL,
  `rsa2_private_key` TEXT NULL,
  `device_main_key` VARCHAR(32) NOT NULL,
  `device_master_secret` VARCHAR(64) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `is_registered` TINYINT(1) DEFAULT 0,
  `key_delivered` TINYINT(1) DEFAULT 0,
  `pkg_name` VARCHAR(255) DEFAULT 'com.colorlinker.puzzle',
  `registration_ip` VARCHAR(45) DEFAULT NULL,
  `rewardbro_uid` VARCHAR(255) DEFAULT NULL,
  `referrer` VARCHAR(255) DEFAULT NULL,
  `gid` VARCHAR(255) DEFAULT NULL,
  `last_request_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_dk_device` (`device_id`),
  INDEX `idx_dk_uid` (`rewardbro_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. Table structure for `user_campaigns`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `user_campaigns` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` VARCHAR(255) NOT NULL UNIQUE,
  `rewardbro_uid` VARCHAR(255) NOT NULL,
  `offer_id` VARCHAR(255) NOT NULL,
  `event_id` VARCHAR(255) DEFAULT NULL,
  `referrer` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_uc_device` (`device_id`),
  INDEX `idx_uc_uid` (`rewardbro_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 7. Table structure for `campaign_clicks`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campaign_clicks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `offer_id` VARCHAR(255) NOT NULL,
  `rewardbro_uid` VARCHAR(255) NOT NULL,
  `event_id` VARCHAR(255) NOT NULL,
  `gaid` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'pending',
  `referrer` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_cc_uid` (`rewardbro_uid`),
  INDEX `idx_cc_gaid` (`gaid`),
  INDEX `idx_cc_ref` (`referrer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 8. Table structure for `level_history`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `level_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` VARCHAR(255) NOT NULL,
  `level` INT NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_device` (`device_id`),
  INDEX `idx_level` (`level`),
  INDEX `idx_dev_created` (`device_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 9. Table structure for `level_bypass_logs`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `level_bypass_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` VARCHAR(255) NOT NULL,
  `attempted_level` INT NOT NULL,
  `current_db_level` INT NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_device_bypass` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 10. Table structure for `postback_settings`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `postback_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `target_level` INT NOT NULL UNIQUE,
  `event_id` VARCHAR(255) NOT NULL,
  `secret_key` VARCHAR(255) NOT NULL,
  `coin` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `postback_settings` (`id`, `target_level`, `event_id`, `secret_key`, `coin`) VALUES
(1, 11, 'event_1780456498800', '69be08a5a3fa6c750f908a4154c0b6b8', 0),
(2, 26, 'event_1780479414678', '69be08a5a3fa6c750f908a4154c0b6b8', 0),
(3, 40, 'event_1780479453011', '69be08a5a3fa6c750f908a4154c0b6b8', 0)
ON DUPLICATE KEY UPDATE target_level=target_level;

-- --------------------------------------------------------
-- 11. Table structure for `postback_history`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `postback_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` VARCHAR(255) NOT NULL,
  `rewardbro_uid` VARCHAR(255) NOT NULL,
  `offer_id` VARCHAR(255) NOT NULL,
  `event_id` VARCHAR(255) NOT NULL,
  `level` INT NOT NULL,
  `postback_url` TEXT NOT NULL,
  `response_status` INT DEFAULT NULL,
  `response_body` TEXT,
  `coin` INT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ph_device` (`device_id`),
  INDEX `idx_ph_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 12. Table structure for `tester_settings`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tester_settings` (
  `id` INT PRIMARY KEY,
  `instant_approval` TINYINT(1) NOT NULL DEFAULT 1,
  `total_days` INT NOT NULL DEFAULT 7,
  `reward_amount` INT NOT NULL DEFAULT 150,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `badge_text` VARCHAR(255) NOT NULL DEFAULT 'EARN ₹150 GUARANTEED',
  `badge_color` VARCHAR(50) NOT NULL DEFAULT '#06D6A0',
  `title` VARCHAR(255) NOT NULL DEFAULT 'Join the 7-Day Testing Team',
  `description` TEXT NULL,
  `perk1_title` VARCHAR(100) NOT NULL DEFAULT '10 Levels/Day',
  `perk1_subtitle` VARCHAR(100) NOT NULL DEFAULT 'Daily Target',
  `perk2_title` VARCHAR(100) NOT NULL DEFAULT 'Bug Reports',
  `perk2_subtitle` VARCHAR(100) NOT NULL DEFAULT 'Quick Review',
  `perk3_title` VARCHAR(100) NOT NULL DEFAULT 'Instant Cash',
  `perk3_subtitle` VARCHAR(100) NOT NULL DEFAULT '₹150 Reward',
  `ticker_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `ticker_show_home` TINYINT(1) NOT NULL DEFAULT 1,
  `ticker_show_register` TINYINT(1) NOT NULL DEFAULT 1,
  `ticker_show_dashboard` TINYINT(1) NOT NULL DEFAULT 1,
  `ticker_speed` INT NOT NULL DEFAULT 25,
  `ticker_custom_items` TEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tester_settings` (`id`, `instant_approval`, `total_days`, `reward_amount`, `is_active`, `badge_text`, `badge_color`, `title`, `description`, `perk1_title`, `perk1_subtitle`, `perk2_title`, `perk2_subtitle`, `perk3_title`, `perk3_subtitle`, `ticker_enabled`, `ticker_speed`) 
VALUES (1, 1, 7, 150, 1, 'EARN ₹150 GUARANTEED', '#06D6A0', 'Join the 7-Day Testing Team', 'Play 10 puzzle levels every day for 7 days, submit short daily bug reviews, and claim direct UPI/Paytm payout on Day 7!', '10 Levels/Day', 'Daily Target', 'Bug Reports', 'Quick Review', 'Instant Cash', '₹150 Reward', 1, 25)
ON DUPLICATE KEY UPDATE id=id;

-- --------------------------------------------------------
-- 13. Table structure for `tester_day_configs`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tester_day_configs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `day_number` INT NOT NULL UNIQUE,
  `required_levels` INT NOT NULL DEFAULT 10,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `instructions` TEXT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tester_day_configs` (`day_number`, `required_levels`, `title`, `instructions`, `status`) VALUES
(1, 10, 'Day 1: Beginners Puzzle Test', 'Complete 10 levels and report any initial bugs.', 1),
(2, 10, 'Day 2: ColorLinker Mechanics Test', 'Complete 10 levels testing speed and touch responsiveness.', 1),
(3, 10, 'Day 3: Combo Release Test', 'Complete 10 levels testing obstacle clearing.', 1),
(4, 10, 'Day 4: Grid Untangle Test', 'Complete 10 levels testing large mazes.', 1),
(5, 10, 'Day 5: Performance & Ad Flow', 'Complete 10 levels testing smooth transitions.', 1),
(6, 10, 'Day 6: Advanced Puzzle Master', 'Complete 10 levels with minimal hints.', 1),
(7, 10, 'Day 7: Final Grandmaster Test', 'Complete final 10 levels and submit your overall review!', 1)
ON DUPLICATE KEY UPDATE day_number=day_number;

-- --------------------------------------------------------
-- 14. Table structure for `tester_users`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tester_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` VARCHAR(255) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `mobile` VARCHAR(50) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `status` ENUM('pending', 'active', 'rejected', 'completed') NOT NULL DEFAULT 'active',
  `is_finished` TINYINT(1) NOT NULL DEFAULT 0,
  `current_day` INT NOT NULL DEFAULT 1,
  `current_cycle` INT NOT NULL DEFAULT 1,
  `registered_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `approved_at` TIMESTAMP NULL,
  `completed_at` TIMESTAMP NULL,
  INDEX `idx_tester_device` (`device_id`),
  INDEX `idx_tester_status` (`status`),
  INDEX `idx_tester_cycle` (`current_cycle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 15. Table structure for `tester_daily_progress`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tester_daily_progress` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tester_user_id` INT NOT NULL,
  `device_id` VARCHAR(255) NOT NULL,
  `cycle` INT NOT NULL DEFAULT 1,
  `day_number` INT NOT NULL,
  `target_levels` INT NOT NULL DEFAULT 10,
  `levels_completed` INT NOT NULL DEFAULT 0,
  `feedback_text` TEXT NULL,
  `status` ENUM('in_progress', 'completed', 'failed') NOT NULL DEFAULT 'in_progress',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `completed_at` TIMESTAMP NULL,
  UNIQUE KEY `uk_tester_day` (`device_id`, `cycle`, `day_number`),
  INDEX `idx_prog_device` (`device_id`),
  INDEX `idx_prog_cycle_status` (`device_id`, `cycle`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 16. Table structure for `tester_reward_claims`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tester_reward_claims` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tester_user_id` INT NOT NULL,
  `device_id` VARCHAR(255) NOT NULL,
  `cycle` INT NOT NULL DEFAULT 1,
  `amount` INT NOT NULL,
  `payment_method` VARCHAR(50) NOT NULL,
  `account_details` VARCHAR(255) NOT NULL,
  `voucher_code` VARCHAR(255) NULL DEFAULT NULL,
  `admin_notes` VARCHAR(255) NULL DEFAULT NULL,
  `status` ENUM('pending', 'sent', 'rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_claim_device` (`device_id`),
  INDEX `idx_claim_status` (`status`),
  INDEX `idx_claim_cycle` (`cycle`),
  INDEX `idx_claim_status_cycle` (`status`, `cycle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 17. Table structure for `tester_payout_methods`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tester_payout_methods` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `input_placeholder` VARCHAR(255) NOT NULL DEFAULT '',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tester_payout_methods` (`name`, `input_placeholder`, `status`, `sort_order`) VALUES
('UPI', 'Enter UPI ID (e.g. name@okhdfcbank)', 1, 1),
('Paytm', 'Enter 10-digit Paytm Wallet/Mobile Number', 1, 2),
('Google Play', 'Enter Gmail Address for Redeem Code', 1, 3),
('Amazon Pay', 'Enter Amazon registered Mobile or Email', 1, 4)
ON DUPLICATE KEY UPDATE name=name;

-- --------------------------------------------------------
-- 18. Table structure for `app_banners`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `app_banners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL DEFAULT '',
  `description` VARCHAR(255) NOT NULL DEFAULT '',
  `image_url` TEXT NOT NULL,
  `action_type` VARCHAR(50) NOT NULL DEFAULT 'tester_program',
  `action_value` TEXT NULL,
  `button_text` VARCHAR(50) NOT NULL DEFAULT 'OPEN >',
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `app_banners` (`id`, `title`, `description`, `image_url`, `action_type`, `action_value`, `button_text`, `sort_order`, `status`) VALUES
(1, 'Join 7-Day Testing Team 🎯', 'Play 10 Levels/Day & Earn ₹150 Guaranteed Direct Payout!', 'https://images.unsplash.com/photo-1511512578047-dfb367046420?w=800&auto=format&fit=crop&q=80', 'tester_program', '', 'START NOW >', 1, 1),
(2, 'Instant Cash & Voucher Rewards 🎁', 'Complete Milestone Levels & Claim UPI / Play Code Rewards!', 'https://images.unsplash.com/photo-1579621970563-ebec7560ff3e?w=800&auto=format&fit=crop&q=80', 'reward_center', '', 'CLAIM ₹150 >', 2, 1)
ON DUPLICATE KEY UPDATE id=id;

-- --------------------------------------------------------
-- 19. Table structure for `install_tasks`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `install_tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `level` INT NOT NULL UNIQUE,
  `required_mb` INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `install_tasks` (`id`, `level`, `required_mb`) VALUES
(1, 15, 10)
ON DUPLICATE KEY UPDATE level=level;

-- --------------------------------------------------------
-- 20. Table structure for `reward_settings`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reward_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `required_level` INT NOT NULL UNIQUE,
  `reward_amount` INT NOT NULL,
  `message` VARCHAR(255) NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `reward_settings` (`id`, `required_level`, `reward_amount`, `message`, `status`) VALUES
(1, 5, 50, 'Level 5 Complete Reward!', 0),
(2, 10, 100, 'Level 10 Complete Reward!', 0)
ON DUPLICATE KEY UPDATE required_level=required_level;

-- --------------------------------------------------------
-- 21. Table structure for `rewards`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `rewards` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` VARCHAR(255) NOT NULL,
  `method` VARCHAR(50) NOT NULL,
  `account` VARCHAR(255) NOT NULL,
  `amount` INT NOT NULL,
  `voucher_code` VARCHAR(255) NULL DEFAULT NULL,
  `admin_notes` VARCHAR(255) NULL DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'completed', 'rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_rwd_device` (`device_id`),
  INDEX `idx_rwd_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 22. Table structure for `security_logs`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `security_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` VARCHAR(255) NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `event_type` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_sl_device` (`device_id`),
  INDEX `idx_sl_type` (`event_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 23. Table structure for `push_notifications`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `push_notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `image_url` TEXT NULL,
  `action_type` VARCHAR(50) NOT NULL DEFAULT 'open_app',
  `action_value` TEXT NULL,
  `button_text` VARCHAR(100) NULL,
  `onesignal_id` VARCHAR(100) NULL,
  `recipients_count` INT DEFAULT 0,
  `status` VARCHAR(50) DEFAULT 'sent',
  `response_raw` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_pn_status` (`status`),
  INDEX `idx_pn_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
