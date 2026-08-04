-- Database Schema for Nuvis Webbuilder Open-Source Commercial Builder
-- Highly Optimized and Secure Structure for PHP 8.1+ & MariaDB/MySQL

-- Users Table (Supports admins and regular commercial builders)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'user', -- 'admin' or 'user'
    `status` VARCHAR(20) NOT NULL DEFAULT 'active', -- 'active', 'suspended'
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB;

-- Projects / Websites Table
CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `content_json` LONGTEXT NULL, -- Drag & Drop canvas state
    `published_html` LONGTEXT NULL, -- Pre-rendered cached HTML output for raw speeds
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft', -- 'draft', 'published'
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_slug` (`user_id`, `slug`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_slug` (`slug`)
) ENGINE=InnoDB;

-- Pre-defined Premium Templates
CREATE TABLE IF NOT EXISTS `templates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `thumbnail_url` VARCHAR(255) NULL,
    `content_json` LONGTEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Contact Submissions Table (Records forms submitted by public users on published websites)
CREATE TABLE IF NOT EXISTS `contact_submissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    INDEX `idx_project_id` (`project_id`)
) ENGINE=InnoDB;

-- Email Dispatch / Notification logs (Simulates commercial server notification logs)
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT NOT NULL,
    `recipient` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(150) NOT NULL,
    `body` TEXT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'sent', -- 'sent', 'failed'
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`submission_id`) REFERENCES `contact_submissions`(`id`) ON DELETE CASCADE,
    INDEX `idx_submission_id` (`submission_id`)
) ENGINE=InnoDB;

-- Global Email settings
CREATE TABLE IF NOT EXISTS `email_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient_email` VARCHAR(255) NOT NULL DEFAULT 'admin@nuvis-webbuilder.io',
    `auto_responder_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `auto_responder_subject` VARCHAR(255) NOT NULL DEFAULT 'Thank you for contacting us!',
    `auto_responder_body` TEXT NOT NULL,
    `template_theme` VARCHAR(50) NOT NULL DEFAULT 'modern_minimalist'
) ENGINE=InnoDB;

-- Page Versioning / Snapshot History Timeline
CREATE TABLE IF NOT EXISTS `project_versions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT NOT NULL,
    `label` VARCHAR(150) NOT NULL,
    `content_json` LONGTEXT NOT NULL,
    `version_type` VARCHAR(50) NOT NULL DEFAULT 'manual', -- 'manual', 'publish', 'auto'
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    INDEX `idx_project_version_id` (`project_id`)
) ENGINE=InnoDB;
