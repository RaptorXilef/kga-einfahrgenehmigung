-- Migration: Anpassung der Voucher-Tabelle an das saubere DDD-Schema
-- Führt die alten Spalten in das neue, strikte Naming-Konzept über.

ALTER TABLE `vouchers`
    CHANGE COLUMN `multi_use` `is_multi_use` TINYINT(1) NOT NULL DEFAULT 0,
    CHANGE COLUMN `uses_count` `current_uses` INT(11) NOT NULL DEFAULT 0,
    CHANGE COLUMN `data` `prefill_data` TEXT DEFAULT NULL;
