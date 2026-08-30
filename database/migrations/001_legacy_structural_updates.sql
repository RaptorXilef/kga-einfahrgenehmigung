-- 001_rename_migrations_table.sql
RENAME TABLE `migrations` TO `update_migrations`;

-- 002_create_update_migrations_table.sql & 003_alter_update_migrations_id.sql
CREATE TABLE IF NOT EXISTS `update_migrations` (
    `id` VARCHAR(50) PRIMARY KEY,
    `version` VARCHAR(50) NOT NULL,
    `executed_at` DATETIME NOT NULL,
    UNIQUE KEY `idx_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 004_add_agreements_column.sql
ALTER TABLE `permits` ADD COLUMN `agreements` JSON NULL;
ALTER TABLE `permits_archive` ADD COLUMN `agreements` JSON NULL;

-- 005_add_is_anonymized_column.sql
ALTER TABLE `permits_archive` ADD COLUMN `is_anonymized` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `permits_archive` ADD INDEX `idx_anonymized` (`is_anonymized`);

-- 008_add_bezahlt_am_column.sql
ALTER TABLE `permits` ADD COLUMN `bezahlt_am` DATETIME NULL DEFAULT NULL AFTER `erstellt`;
ALTER TABLE `permits_archive` ADD COLUMN `bezahlt_am` DATETIME NULL DEFAULT NULL AFTER `erstellt`;

-- 009_create_cancelled_permits_table.sql
CREATE TABLE IF NOT EXISTS `permits_cancelled` (
    `code` VARCHAR(50) NOT NULL,
    `template_key` VARCHAR(50) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) DEFAULT NULL,
    `kennzeichen` VARCHAR(20) DEFAULT NULL,
    `parzelle` INT NOT NULL,
    `typ` VARCHAR(20) NOT NULL,
    `firma` VARCHAR(255) DEFAULT NULL,
    `zweck` VARCHAR(255) NOT NULL,
    `preis` DECIMAL(10,2) NOT NULL,
    `von` DATE NOT NULL,
    `bis` DATE NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'storniert',
    `erstellt` DATETIME NOT NULL,
    `interner_kommentar` TEXT DEFAULT NULL,
    `is_anonymized` TINYINT(1) NOT NULL DEFAULT 1,
    `is_suspended` TINYINT(1) NOT NULL DEFAULT 0,
    `suspension_reason` TEXT DEFAULT NULL,
    `agreements` JSON DEFAULT NULL,
    `bezahlt_am` DATETIME DEFAULT NULL,
    `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 011_add_reminder_sent_column.sql
ALTER TABLE `permits` ADD COLUMN `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `permits` ADD INDEX `idx_reminder_sent` (`reminder_sent`);
ALTER TABLE `permits_archive` ADD COLUMN `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `permits_cancelled` ADD COLUMN `reminder_sent` TINYINT(1) NOT NULL DEFAULT 0;

-- 012_fix_mail_tables.sql
ALTER TABLE `mail_logs` MODIFY COLUMN `id` VARCHAR(50) NOT NULL;
ALTER TABLE `mail_queue` MODIFY COLUMN `id` VARCHAR(50) NOT NULL;

-- 013_add_suspension_to_archives.sql
ALTER TABLE `permits_archive` ADD COLUMN `is_suspended` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `permits_archive` ADD COLUMN `suspension_reason` TEXT DEFAULT NULL;
ALTER TABLE `permits_cancelled` ADD COLUMN `is_suspended` TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE `permits_cancelled` ADD COLUMN `suspension_reason` TEXT DEFAULT NULL;

-- 014_create_audit_logs_table.sql
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` VARCHAR(50) PRIMARY KEY,
    `user_id` VARCHAR(50) NOT NULL,
    `username` VARCHAR(100) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `details` TEXT,
    `ip_address` VARCHAR(45),
    `created_at` DATETIME NOT NULL,
    INDEX `idx_action` (`action`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 016_migrate_plot_to_int.sql
ALTER TABLE `permits` MODIFY COLUMN `parzelle` INT NOT NULL DEFAULT 0;
ALTER TABLE `permits_archive` MODIFY COLUMN `parzelle` INT NOT NULL DEFAULT 0;
ALTER TABLE `permits_cancelled` MODIFY COLUMN `parzelle` INT NOT NULL DEFAULT 0;
ALTER TABLE `vouchers_archive` MODIFY COLUMN `user_plot` INT NULL DEFAULT NULL;

-- 017_fix_template_keys.sql
UPDATE `permits` SET `template_key` = REPLACE(`template_key`, '.', '_') WHERE `template_key` LIKE '%.%';
UPDATE `permits_archive` SET `template_key` = REPLACE(`template_key`, '.', '_') WHERE `template_key` LIKE '%.%';
UPDATE `permits_cancelled` SET `template_key` = REPLACE(`template_key`, '.', '_') WHERE `template_key` LIKE '%.%';
UPDATE `groups` SET `permissions` = REPLACE(`permissions`, 'template.std.', 'template.std_') WHERE `permissions` LIKE '%template.std.%';
UPDATE `groups` SET `permissions` = REPLACE(`permissions`, 'template.perm.', 'template.perm_') WHERE `permissions` LIKE '%template.perm.%';
UPDATE `groups` SET `permissions` = REPLACE(`permissions`, 'template.custom.', 'template.custom_') WHERE `permissions` LIKE '%template.custom.%';

-- 018_rename_groups_to_roles.sql
RENAME TABLE `groups` TO `roles`;
UPDATE `roles` SET `permissions` = REPLACE(`permissions`, 'system.permissions.groups.', 'system.permissions.roles.');
UPDATE `roles` SET `permissions` = REPLACE(`permissions`, 'dashboard.migration.groups.', 'dashboard.migration.roles.');
