-- =============================================================================
-- WebCraft Database Schema  v2.0
-- MySQL / MariaDB  |  PHP 7.4+
-- Changes from v1:
--   + page_versions  : draft/publish history per project
--   + project_assets : uploaded images / files per project
--   + api_keys       : per-project token auth for 3rd-party integrations
--   + projects.schema_version : tracks which builder version produced the JSON
--   - projects.published_html : removed (render.php compiles live from JSON)
--   * templates seed data      : starter "Blank" and "Landing Page" templates
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `site_builder`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `site_builder`;

-- -----------------------------------------------------------------------------
-- USERS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT          AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(50)  NOT NULL UNIQUE,
    `email`         VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role`          VARCHAR(20)  NOT NULL DEFAULT 'user',    -- 'admin' | 'user'
    `status`        VARCHAR(20)  NOT NULL DEFAULT 'active',  -- 'active' | 'suspended'
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_username` (`username`),
    INDEX `idx_email`    (`email`)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- PROJECTS  (one site per row)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
    `id`             INT          AUTO_INCREMENT PRIMARY KEY,
    `user_id`        INT          NOT NULL,
    `name`           VARCHAR(100) NOT NULL,
    `slug`           VARCHAR(100) NOT NULL,
    `description`    VARCHAR(255) NULL,
    -- v2 schema: { version, meta:{title,description,custom_css,custom_js}, blocks:[] }
    `content_json`   LONGTEXT     NULL,
    -- schema_version: 1 = legacy flat array, 2 = v2 blocks schema
    `schema_version` TINYINT      NOT NULL DEFAULT 2,
    `status`         VARCHAR(20)  NOT NULL DEFAULT 'draft',  -- 'draft' | 'published'
    `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_slug` (`user_id`, `slug`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_slug`    (`slug`),
    INDEX `idx_status`  (`status`)
) ENGINE=InnoDB;

-- Migration note for existing installs:
-- ALTER TABLE `projects` ADD COLUMN `schema_version` TINYINT NOT NULL DEFAULT 2 AFTER `content_json`;
-- ALTER TABLE `projects` DROP COLUMN IF EXISTS `published_html`;

-- -----------------------------------------------------------------------------
-- PAGE VERSIONS  (history snapshots — draft & publish events)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `page_versions` (
    `id`          INT       AUTO_INCREMENT PRIMARY KEY,
    `project_id`  INT       NOT NULL,
    `schema_json` LONGTEXT  NOT NULL,   -- full v2 schema snapshot
    `status`      VARCHAR(20) NOT NULL DEFAULT 'draft',  -- 'draft' | 'published'
    `created_by`  INT       NOT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)    ON DELETE CASCADE,
    INDEX `idx_pv_project` (`project_id`),
    INDEX `idx_pv_status`  (`status`)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- PROJECT ASSETS  (uploaded images / files per project)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `project_assets` (
    `id`          INT          AUTO_INCREMENT PRIMARY KEY,
    `project_id`  INT          NOT NULL,
    `uploaded_by` INT          NOT NULL,
    `filename`    VARCHAR(255) NOT NULL,              -- original filename
    `file_url`    VARCHAR(500) NOT NULL,              -- relative public URL
    `mime_type`   VARCHAR(100) NOT NULL DEFAULT '',
    `file_size`   INT          NOT NULL DEFAULT 0,    -- bytes
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`)  REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`)    ON DELETE CASCADE,
    INDEX `idx_pa_project` (`project_id`)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- API KEYS  (per-project token auth for 3rd-party integrations)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id`          INT          AUTO_INCREMENT PRIMARY KEY,
    `project_id`  INT          NOT NULL,
    `user_id`     INT          NOT NULL,
    `key_hash`    VARCHAR(255) NOT NULL UNIQUE,  -- SHA-256 of the raw token
    `label`       VARCHAR(100) NOT NULL DEFAULT 'Default Key',
    `scopes`      VARCHAR(255) NOT NULL DEFAULT 'read',  -- 'read' | 'write' | 'read,write'
    `last_used_at` TIMESTAMP   NULL,
    `expires_at`  TIMESTAMP    NULL,
    `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)    REFERENCES `users`(`id`)    ON DELETE CASCADE,
    INDEX `idx_ak_project` (`project_id`),
    INDEX `idx_ak_key`     (`key_hash`)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- TEMPLATES  (pre-built starter layouts)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `templates` (
    `id`            INT          AUTO_INCREMENT PRIMARY KEY,
    `name`          VARCHAR(100) NOT NULL UNIQUE,
    `description`   VARCHAR(255) NULL,
    `thumbnail_url` VARCHAR(255) NULL,
    `content_json`  LONGTEXT     NOT NULL,
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Seed: Blank canvas template
INSERT IGNORE INTO `templates` (`name`, `description`, `thumbnail_url`, `content_json`) VALUES (
  'Blank Canvas',
  'Start from scratch with an empty page.',
  NULL,
  '{"version":1,"meta":{"title":"My Site","description":"","custom_css":"","custom_js":""},"blocks":[]}'
);

-- Seed: Landing page starter template
INSERT IGNORE INTO `templates` (`name`, `description`, `thumbnail_url`, `content_json`) VALUES (
  'Landing Page',
  'A ready-made landing page with Navbar, Hero, Features, CTA and Footer.',
  NULL,
  '{"version":1,"meta":{"title":"My Landing Page","description":"Built with WebCraft","custom_css":"","custom_js":""},"blocks":[{"id":"blk_001","type":"navbar","props":{"brand":"WebCraft","logo_url":"","bg":"bg-slate-900","links":[{"label":"Home","href":"#"},{"label":"Features","href":"#features"},{"label":"Pricing","href":"#pricing"},{"label":"Contact","href":"#contact"}]}},{"id":"blk_002","type":"hero","props":{"heading":"Build Beautiful Websites","subheading":"No code required. Drag, drop, and publish in minutes.","button_text":"Get Started","button_href":"#","bg":"bg-indigo-950","align":"text-center","padding":"py-28"}},{"id":"blk_003","type":"features_grid","props":{"heading":"Why WebCraft?","bg":"bg-slate-900","padding":"py-16","features":[{"icon":"fas fa-bolt","title":"Fast","desc":"Lightning-fast performance out of the box."},{"icon":"fas fa-lock","title":"Secure","desc":"Enterprise-grade security built in."},{"icon":"fas fa-expand","title":"Scalable","desc":"Grows seamlessly with your business."}]}},{"id":"blk_004","type":"cta_banner","props":{"heading":"Ready to get started?","subtext":"Join thousands of builders today.","button_text":"Start Free","button_href":"#","bg":"bg-teal-900"}},{"id":"blk_005","type":"footer","props":{"brand":"WebCraft","logo_url":"","copyright":"\u00a9 2026 WebCraft. All rights reserved.","links":[{"label":"Privacy","href":"#"},{"label":"Terms","href":"#"},{"label":"Support","href":"#"}]}}]}'
);

-- -----------------------------------------------------------------------------
-- CONTACT SUBMISSIONS  (forms submitted on published sites)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_submissions` (
    `id`         INT  AUTO_INCREMENT PRIMARY KEY,
    `project_id` INT  NOT NULL,
    `name`       VARCHAR(100) NOT NULL,
    `email`      VARCHAR(100) NOT NULL,
    `message`    TEXT         NOT NULL,
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    INDEX `idx_cs_project` (`project_id`)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- EMAIL LOGS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_logs` (
    `id`            INT  AUTO_INCREMENT PRIMARY KEY,
    `submission_id` INT  NOT NULL,
    `recipient`     VARCHAR(100) NOT NULL,
    `subject`       VARCHAR(150) NOT NULL,
    `body`          TEXT         NOT NULL,
    `status`        VARCHAR(20)  NOT NULL DEFAULT 'sent',  -- 'sent' | 'failed'
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`submission_id`) REFERENCES `contact_submissions`(`id`) ON DELETE CASCADE,
    INDEX `idx_el_submission` (`submission_id`)
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- MIGRATION SCRIPT  (run once on existing v1 installs)
-- Safe to run — all statements use IF EXISTS / IF NOT EXISTS guards.
-- -----------------------------------------------------------------------------
/*
  -- 1. Add schema_version column if missing
  ALTER TABLE `projects`
    ADD COLUMN IF NOT EXISTS `schema_version` TINYINT NOT NULL DEFAULT 2
    AFTER `content_json`;

  -- 2. Drop the now-unused published_html column
  ALTER TABLE `projects`
    DROP COLUMN IF EXISTS `published_html`;

  -- 3. Create new tables (safe — uses CREATE TABLE IF NOT EXISTS above)

  -- 4. Mark existing rows as schema v1 so render.php applies legacy handling
  UPDATE `projects` SET `schema_version` = 1
  WHERE `content_json` IS NOT NULL
    AND JSON_VALID(`content_json`)
    AND JSON_TYPE(`content_json`) = 'ARRAY';
*/
