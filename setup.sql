-- AI Office — ডাটাবেস স্কিমা
-- Run this via install.php or manually if you prefer.

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(64) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `agent_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `agent_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent` VARCHAR(50) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `input_summary` TEXT,
  `output_summary` TEXT,
  `status` ENUM('success','error','pending') DEFAULT 'success',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_agent` (`agent`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `agent_state` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `agent` VARCHAR(50) NOT NULL UNIQUE,
  `state` ENUM('idle','working','error') DEFAULT 'idle',
  `last_run` DATETIME NULL,
  `last_output` TEXT,
  `run_count` INT DEFAULT 0,
  `error_count` INT DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `agent_orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `woo_order_id` INT,
  `customer_name` VARCHAR(255),
  `customer_email` VARCHAR(255),
  `total` DECIMAL(10,2),
  `status` VARCHAR(50) DEFAULT 'pending',
  `bk_formatted` TEXT,
  `forwarded_at` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_woo_order` (`woo_order_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role` ENUM('user','assistant') NOT NULL,
  `content` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cart_recovery` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `customer_email` VARCHAR(255) NOT NULL,
  `customer_name` VARCHAR(255),
  `cart_data` JSON,
  `step` TINYINT DEFAULT 0,
  `last_sent` DATETIME NULL,
  `purchased` TINYINT DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `catalog_import` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(255),
  `total_rows` INT DEFAULT 0,
  `imported` INT DEFAULT 0,
  `skipped` INT DEFAULT 0,
  `status` ENUM('pending','processing','done','error') DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cron_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `job` VARCHAR(100) NOT NULL,
  `result` TEXT,
  `ran_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ডিফল্ট সেটিংস
INSERT IGNORE INTO `agent_settings` (`setting_key`, `setting_value`) VALUES
  ('profit_margin', '30'),
  ('bk_id', ''),
  ('bk_phone', ''),
  ('ai_model', 'llama-3.3-70b-versatile'),
  ('store_name', 'আমার স্টোর'),
  ('currency', 'BDT'),
  ('language', 'bn'),
  ('cart_step1_hours', '1'),
  ('cart_step2_hours', '24'),
  ('cart_step3_hours', '72'),
  ('social_platforms', 'facebook,instagram'),
  ('seo_target_score', '80');
