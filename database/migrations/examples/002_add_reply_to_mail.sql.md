ALTER TABLE `mail_queue` ADD COLUMN `reply_to` VARCHAR(255) DEFAULT NULL AFTER `recipient`;
ALTER TABLE `mail_logs` ADD COLUMN `reply_to` VARCHAR(255) DEFAULT NULL AFTER `recipient`;
