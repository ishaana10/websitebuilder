-- Database Schema for Nuvis Webidesigner Open-Source Commercial Builder
-- Highly Optimized and Secure Structure for PHP 8.1+ & MariaDB/MySQL

-- Tenants Table (SaaS Layer)
CREATE TABLE IF NOT EXISTS `tenants` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `subdomain` VARCHAR(50) NOT NULL UNIQUE,
    `custom_domain` VARCHAR(100) NULL UNIQUE,
    `subscription_plan` VARCHAR(20) NOT NULL DEFAULT 'free', -- 'free', 'pro', 'agency', 'white-label'
    `billing_status` VARCHAR(20) NOT NULL DEFAULT 'active', -- 'active', 'past_due', 'canceled'
    `stripe_customer_id` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_subdomain` (`subdomain`),
    INDEX `idx_custom_domain` (`custom_domain`)
) ENGINE=InnoDB;

-- Users Table (Supports admins and regular commercial builders)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NULL, -- NULL for global system admins
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` VARCHAR(20) NOT NULL DEFAULT 'user', -- 'admin', 'Owner', 'Editor', 'Designer', 'Viewer'
    `status` VARCHAR(20) NOT NULL DEFAULT 'active', -- 'active', 'suspended'
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE SET NULL,
    INDEX `idx_username` (`username`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB;

-- Projects / Websites Table (With Multi-Tenancy & SEO properties)
CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `tenant_id` INT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(100) NOT NULL,
    `description` VARCHAR(255) NULL,
    `content_json` LONGTEXT NULL, -- Drag & Drop canvas state
    `published_html` LONGTEXT NULL, -- Pre-rendered cached HTML output for raw speeds
    `status` VARCHAR(20) NOT NULL DEFAULT 'draft', -- 'draft', 'published'

    -- SEO Metadata Fields
    `seo_title` VARCHAR(255) NULL,
    `seo_meta_desc` VARCHAR(255) NULL,
    `seo_og_image` VARCHAR(255) NULL,
    `seo_favicon` VARCHAR(255) NULL,
    `seo_robots_txt` TEXT NULL,
    `seo_structured_data` TEXT NULL,

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_slug` (`user_id`, `slug`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_tenant_id` (`tenant_id`),
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
    `tenant_id` INT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_project_id` (`project_id`),
    INDEX `idx_tenant_id` (`tenant_id`)
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
    `recipient_email` VARCHAR(255) NOT NULL DEFAULT 'admin@nuvis-webidesigner.io',
    `auto_responder_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `auto_responder_subject` VARCHAR(255) NOT NULL DEFAULT 'Thank you for contacting us!',
    `auto_responder_body` TEXT NOT NULL,
    `template_theme` VARCHAR(50) NOT NULL DEFAULT 'modern_minimalist',
    `smtp_host` VARCHAR(255) NULL,
    `smtp_port` INT NULL,
    `smtp_username` VARCHAR(255) NULL,
    `smtp_password` VARCHAR(255) NULL,
    `smtp_encryption` VARCHAR(10) NULL,
    `smtp_from_email` VARCHAR(255) NULL,
    `smtp_from_name` VARCHAR(255) NULL
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

-- System Debug Logs Table
CREATE TABLE IF NOT EXISTS `system_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `log_level` VARCHAR(20) NOT NULL DEFAULT 'info', -- 'info', 'warning', 'error', 'debug'
    `message` TEXT NOT NULL,
    `context` TEXT NULL, -- Additional JSON or context details
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_log_level` (`log_level`)
) ENGINE=InnoDB;

-- E-commerce Product Catalog Table
CREATE TABLE IF NOT EXISTS `ecommerce_products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `sku` VARCHAR(50) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `description` TEXT NULL,
    `image_url` VARCHAR(255) NULL,
    `stock` INT NOT NULL DEFAULT 10,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_ecommerce_tenant` (`tenant_id`)
) ENGINE=InnoDB;

-- E-commerce Orders Table
CREATE TABLE IF NOT EXISTS `ecommerce_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_email` VARCHAR(100) NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `payment_status` VARCHAR(20) NOT NULL DEFAULT 'pending', -- 'pending', 'paid', 'failed'
    `shipping_address` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_orders_tenant` (`tenant_id`)
) ENGINE=InnoDB;

-- Blog Posts Table
CREATE TABLE IF NOT EXISTS `blog_posts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(150) NOT NULL,
    `content` LONGTEXT NOT NULL,
    `excerpt` TEXT NULL,
    `image_url` VARCHAR(255) NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'General',
    `tags` VARCHAR(255) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'published', -- 'draft', 'published'
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_blog_tenant` (`tenant_id`)
) ENGINE=InnoDB;

-- Blog Comments Table
CREATE TABLE IF NOT EXISTS `blog_comments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `post_id` INT NOT NULL,
    `author_name` VARCHAR(100) NOT NULL,
    `author_email` VARCHAR(100) NOT NULL,
    `comment_text` TEXT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'approved', -- 'pending', 'approved', 'moderated'
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`post_id`) REFERENCES `blog_posts`(`id`) ON DELETE CASCADE,
    INDEX `idx_comment_post` (`post_id`)
) ENGINE=InnoDB;

-- Booking Schedules / Calendar Table
CREATE TABLE IF NOT EXISTS `booking_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `customer_name` VARCHAR(100) NOT NULL,
    `customer_email` VARCHAR(100) NOT NULL,
    `booking_date` DATE NOT NULL,
    `booking_time` VARCHAR(10) NOT NULL, -- e.g. '10:00 AM'
    `service_name` VARCHAR(150) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'confirmed', -- 'pending', 'confirmed', 'canceled'
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_booking_tenant` (`tenant_id`)
) ENGINE=InnoDB;

-- CRM Leads / Contact Opportunities Table
CREATE TABLE IF NOT EXISTS `crm_leads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `source` VARCHAR(50) NOT NULL DEFAULT 'Contact Form',
    `status` VARCHAR(20) NOT NULL DEFAULT 'New', -- 'New', 'Contacted', 'Qualified', 'Lost'
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    INDEX `idx_crm_tenant` (`tenant_id`)
) ENGINE=InnoDB;

-- SaaS Usage Metering table
CREATE TABLE IF NOT EXISTS `usage_meters` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `sites_count` INT NOT NULL DEFAULT 0,
    `storage_used_bytes` BIGINT NOT NULL DEFAULT 0,
    `bandwidth_used_bytes` BIGINT NOT NULL DEFAULT 0,
    `ai_calls_count` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_usage_tenant` (`tenant_id`)
) ENGINE=InnoDB;

-- billing transactions
CREATE TABLE IF NOT EXISTS `billing_transactions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tenant_id` INT NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
    `transaction_type` VARCHAR(50) NOT NULL, -- 'subscription', 'metered', 'upgrade'
    `stripe_invoice_id` VARCHAR(100) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
