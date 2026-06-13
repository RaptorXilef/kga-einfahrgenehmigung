<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Core\Service\AuthService;

/**
 * Bootstrapper für die Initialisierung der Speicher-Infrastruktur.
 * Stellt sicher, dass Datenbanktabellen oder JSON-Dateien beim Start vorhanden sind,
 * und führt bei Bedarf initiale Auto-Setups aus.
 *
 * Path: src/Infrastructure/Maintenance/StorageBootstrapper.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class StorageBootstrapper
{
    public function __construct(
        private ?\PDO $pdo,
        private AuthService $authService,
        private ConfigInterface $config,
        private GroupRepositoryInterface $groupRepository,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Öffentlicher Einstieg
     */
    public function bootstrap(): void
    {
        // 1. Wenn MySQL konfiguriert ist, Tabellen sicherstellen
        if ($this->pdo instanceof \PDO) {
            $schema = $this->config->get('db_schema', []);
            foreach ($schema as $tableName => $sql) {
                try {
                    $this->pdo->exec($sql);
                } catch (\PDOException $e) {
                    \error_log("Bootstrap: Fehler beim Erstellen der Tabelle '$tableName': " . $e->getMessage());
                }
            }
        }

        // 2. Nur Core-Strukturen (Users/Groups) initial mit Defaults befüllen, falls komplett leer
        $this->initDefaultGroupsAndUsers();

        // Veraltete Berechtigungen direkt beim Start bereinigen
        $this->cleanupOrphanedPermissions();

        // Stellt sicher, dass das Storage-Verzeichnis absolut dicht ist
        $this->ensureStorageSecurity();
    }

    /**
     * Erstellt oder aktualisiert automatisch eine restriktive .htaccess im storage/ Verzeichnis,
     * um direkten HTTP-Zugriff auf Backups, Logs und JSON-DBs zu blockieren.
     */
    private function ensureStorageSecurity(): void
    {
        $storageDir   = \rtrim((string) $this->config->get('root_path'), '/\\') . '/storage';
        $htaccessPath = $storageDir . '/.htaccess';

        if (! \is_dir($storageDir)) {
            @\mkdir($storageDir, 0o755, true);
        }

        // Der aktuell gewünschte Zustand der Datei
        $expectedContent = "# AUTO-GENERATED SECURITY FILE\n" .
            "# Verhindert jeglichen direkten HTTP-Zugriff auf Logs und Backups.\n" .
            "Order Allow,Deny\n" .
            "Deny from all\n\n" .
            "Options -Indexes\n";

        // Prüfen, ob die Datei fehlt ODER der Inhalt veraltet/verändert ist
        if (! \file_exists($htaccessPath) || \file_get_contents($htaccessPath) !== $expectedContent) {
            @\file_put_contents($htaccessPath, $expectedContent, \LOCK_EX);
        }
    }

    /**
     * Entfernt verwaiste Rechte-Keys aus den Gruppen, die in der aktuellen
     * permissions.php nicht mehr existieren (Schatten-Rechte).
     */
    private function cleanupOrphanedPermissions(): void
    {
        $groups = $this->groupRepository->loadAll();
        if (empty($groups)) {
            return;
        }

        // Alle gültigen Basis-Keys aus der aktuellen Config holen
        // app.php generiert bereits ein flaches Array in 'permissions'
        $validKeys   = \array_keys($this->config->get('permissions', []));
        $validKeys[] = '*'; // Der globale Wildcard ist immer erlaubt

        $changed = false;

        // Referenz (&) nutzen, um das Array direkt zu modifizieren
        foreach ($groups as $id => &$group) {
            if (! isset($group['permissions']) || ! \is_array($group['permissions'])) {
                continue;
            }

            $originalCount = \count($group['permissions']);
            $cleanedPerms  = [];

            foreach ($group['permissions'] as $perm) {
                // Deny-Prefix (-) für den Abgleich entfernen
                $basePerm = \ltrim($perm, '-');

                if (\in_array($basePerm, $validKeys, true)) {
                    $cleanedPerms[] = $perm;
                }
            }

            if (\count($cleanedPerms) !== $originalCount) {
                $group['permissions'] = \array_values($cleanedPerms); // Indizes neu ordnen
                $changed              = true;
            }
        }

        if ($changed) {
            \error_log('Bootstrap: Veraltete Berechtigungen (Orphaned Permissions) wurden erfolgreich bereinigt.');
            $this->groupRepository->saveAll($groups);
        }
    }

    /**
     * Initialisiert Standard-Gruppen und einen Standard-Admin,
     * falls das System (Datenbank oder JSON) komplett leer ist.
     */
    private function initDefaultGroupsAndUsers(): void
    {
        // Wir prüfen, ob die Benutzerverwaltung komplett leer ist (egal ob JSON oder SQL aktiv ist)
        // Nutzt die vorhandenen loadUsers/loadGroups Methoden aus deinem AuthService
        $currentUsers  = $this->userRepository->loadAll();
        $currentGroups = $this->groupRepository->loadAll();

        if (empty($currentGroups)) {
            \error_log('Bootstrap: Initialisiere Standard-Gruppen.');
            $this->groupRepository->saveAll($this->getDefaultGroups());
        }
        if (empty($currentUsers)) {
            \error_log('Bootstrap: Initialisiere Standard-Admin.');
            $this->userRepository->saveAll($this->getDefaultUsers());
        }
    }

    /**
     * Liefert die Zugangsdaten für den Standard-Administrator (Systembetreuer).
     *
     * @return array<string, array<string, mixed>> Der initiale Benutzer.
     */
    private function getDefaultUsers(): array
    {
        return [
            'usr_7c13b491' => [
                'username' => 'Admin',
                'group'    => 'grp_71cb1c0d',
                'pass'     => '$2y$12$DHelEqSuvcbbGPYWqnIrIOfs/PYaMVfyahWHkW.aRM43syMd5ASoW',
            ],
        ];
    }

    /**
     * Liefert die Berechtigungs-Struktur der Standard-Gruppen (Admin, Finanzen, etc.).
     *
     * @return array<string, array<string, mixed>> Die Standard-Gruppen.
     */
    private function getDefaultGroups(): array
    {
        // phpcs:disable Generic.Files.LineLength.TooLong
        return [
            'grp_71cb1c0d' => ['name' => 'Administrator', 'permissions' => ['*']],
            'grp_180a3ec6' => ['name' => 'Finanzen', 'permissions' => ['privacy.finance.reveal', 'privacy.email.reveal', 'check.admin.print', 'dashboard.view', 'dashboard.control_bar.view', 'dashboard.control_bar.future', 'dashboard.control_bar.search', 'dashboard.info_alert.view', 'dashboard.info_alert.print', 'dashboard.info_alert.details', 'dashboard.active.view', 'dashboard.active.print', 'dashboard.active.details', 'dashboard.active.suspend', 'dashboard.finance.view', 'dashboard.finance.details', 'dashboard.finance.suspend', 'dashboard.finance.mark_paid', 'dashboard.future.view', 'dashboard.future.print', 'dashboard.future.details', 'dashboard.future.suspend', 'dashboard.expired.view', 'dashboard.expired.print', 'dashboard.expired.details', 'dashboard.stats.view', 'dashboard.stats.current', 'dashboard.stats.charts', 'dashboard.stats.history', 'dashboard.ranking.view', 'dashboard.export.view', 'finance.export.execute', 'dashboard.export.csv', 'dashboard.export.json', 'dashboard.vouchers.view', 'dashboard.vouchers.open', 'dashboard.vouchers.suspend', 'dashboard.vouchers.remove', 'dashboard.vouchers.archive', 'dashboard.generator-tools.view', 'dashboard.generator-tools.direct_issue.reveal', 'dashboard.generator-tools.direct_issue.execute', 'dashboard.generator-tools.voucher_gen.reveal', 'dashboard.generator-tools.voucher_gen.execute', 'template.manage', 'template.std.7', 'template.std.14', 'template.std.30', 'template.perm.3', 'template.perm.6', 'template.perm.9', 'template.perm.12', 'template.custom.std', 'template.custom.perm']],
            'grp_fd72d38c' => ['name' => 'Sachbearbeitung', 'permissions' => ['privacy.email.reveal', 'check.admin.print', 'dashboard.view', 'dashboard.control_bar.view', 'dashboard.control_bar.future', 'dashboard.control_bar.search', 'dashboard.info_alert.view', 'dashboard.info_alert.print', 'dashboard.info_alert.details', 'dashboard.active.view', 'dashboard.active.print', 'dashboard.active.details', 'dashboard.finance.view', 'dashboard.finance.details', 'dashboard.future.view', 'dashboard.future.print', 'dashboard.future.details', 'dashboard.expired.view', 'dashboard.vouchers.view', 'dashboard.vouchers.open', 'dashboard.vouchers.suspend', 'dashboard.generator-tools.view', 'dashboard.generator-tools.direct_issue.reveal', 'dashboard.generator-tools.direct_issue.execute', 'dashboard.generator-tools.voucher_gen.reveal', 'dashboard.generator-tools.voucher_gen.execute', 'dashboard.logs.view', 'template.manage', 'template.std.7', 'template.std.14', 'template.std.30', 'template.perm.3', 'template.perm.6', 'template.perm.9', 'template.perm.12', 'template.custom.std', 'template.custom.perm']],
            'grp_a53d6b56' => ['name' => 'Prüfer vor Ort', 'permissions' => ['dashboard.view', 'dashboard.control_bar.view', 'dashboard.control_bar.future', 'dashboard.control_bar.search', 'dashboard.active.view', 'dashboard.active.details']],
        ];
        // phpcs:enable Generic.Files.LineLength.TooLong
    }
}
