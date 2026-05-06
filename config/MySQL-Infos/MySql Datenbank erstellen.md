# Die Datenbank-Struktur (SQL)

Aktuell noch manuell:

Damit MySQL weiß, wo es die Daten speichern soll, musst du die Tabelle einmalig in deiner Datenbank (z.B. via phpMyAdmin) anlegen. Basierend auf unserem `StorageMapperTrait` sieht das SQL-Statement so aus:

``` SQL
-- 1. Users
CREATE TABLE `users` (
    `username` VARCHAR(50) PRIMARY KEY,
    `level` INT NOT NULL,
    `label` VARCHAR(100),
    `pass` VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- 2. Vouchers
CREATE TABLE `vouchers` (
    `code` VARCHAR(50) PRIMARY KEY,
    `reason` VARCHAR(255),
    `template_key` VARCHAR(50),
    `type` VARCHAR(20),
    `value` DECIMAL(10,2),
    `multi_use` TINYINT(1),
    `max_uses` INT,
    `uses_count` INT DEFAULT 0,
    `expires_at` DATETIME NULL,
    `date_mode` VARCHAR(20),
    `created_by` VARCHAR(50),
    `created_at` DATETIME,
    `data` TEXT -- Serialisierte Prefill-Daten
) ENGINE=InnoDB;

-- 3. Mail Log
CREATE TABLE `mail_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `timestamp` DATETIME,
    `recipient` VARCHAR(255),
    `subject` VARCHAR(255),
    `template` VARCHAR(100),
    `status` TEXT
) ENGINE=InnoDB;

-- 4. Magic Links
CREATE TABLE `magic_links` (
    `token` VARCHAR(64) PRIMARY KEY,
    `email` VARCHAR(255),
    `code` VARCHAR(10),
    `expires` INT
) ENGINE=InnoDB;

-- 5. Pending Verifications (Warteraum 1)
CREATE TABLE `pending_verifications` (
    `token` VARCHAR(64) PRIMARY KEY,
    `expires` INT,
    `data` LONGTEXT -- Der gesamte Antrag als JSON-String
) ENGINE=InnoDB;

-- 6. Permits (Haupt-Datenbank)
CREATE TABLE `permits` (
  `code` varchar(50) NOT NULL,
  `templateKey` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `kennzeichen` varchar(20) DEFAULT NULL,
  `parzelle` varchar(10) NOT NULL,
  `typ` varchar(20) NOT NULL,
  `firma` varchar(255) DEFAULT NULL,
  `zweck` varchar(255) NOT NULL,
  `preisSnapshot` decimal(10,2) NOT NULL,
  `von` date NOT NULL,
  `bis` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'wartend',
  `isSuspended` tinyint(1) NOT NULL DEFAULT 0,
  `suspensionReason` text DEFAULT NULL,
  `erstellt` datetime NOT NULL,
  `internerKommentar` text DEFAULT NULL,
  PRIMARY KEY (`code`),
  KEY `idx_kennzeichen` (`kennzeichen`),
  KEY `idx_parzelle` (`parzelle`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Warteraum 2
CREATE TABLE `verified_pending` (
    `token` VARCHAR(64) PRIMARY KEY,
    `expires` INT,
    `data` LONGTEXT
) ENGINE=InnoDB;

-- 8. Gutschein-Archiv
CREATE TABLE `vouchers_archive` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(50),
    `redeemed_at` DATETIME,
    `user_name` VARCHAR(255),
    `user_plot` VARCHAR(10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
