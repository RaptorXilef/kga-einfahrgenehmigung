-- 1. Neue Spalten für das intelligente Mahnwesen hinzufügen
ALTER TABLE `permits` ADD COLUMN `last_reminder_at` DATETIME DEFAULT NULL AFTER `bezahlt_am`;
ALTER TABLE `permits_archive` ADD COLUMN `last_reminder_at` DATETIME DEFAULT NULL AFTER `bezahlt_am`;
ALTER TABLE `permits_cancelled` ADD COLUMN `last_reminder_at` DATETIME DEFAULT NULL AFTER `bezahlt_am`;

-- 2. Bestehende "Ja"-Markierungen auf ein fiktives Datum in der Vergangenheit setzen,
-- damit sie nicht sofort heute vom Cronjob gespammt werden
UPDATE `permits` SET `last_reminder_at` = DATE_SUB(NOW(), INTERVAL 8 DAY) WHERE `reminder_sent` = 1;
UPDATE `permits_archive` SET `last_reminder_at` = DATE_SUB(NOW(), INTERVAL 8 DAY) WHERE `reminder_sent` = 1;
UPDATE `permits_cancelled` SET `last_reminder_at` = DATE_SUB(NOW(), INTERVAL 8 DAY) WHERE `reminder_sent` = 1;

-- 3. Die alte, simple Spalte löschen
ALTER TABLE `permits` DROP COLUMN `reminder_sent`;
ALTER TABLE `permits_archive` DROP COLUMN `reminder_sent`;
ALTER TABLE `permits_cancelled` DROP COLUMN `reminder_sent`;
