<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\PermitService;

/**
 * Scheduler für automatisierte Wartungsaufgaben (Pseudo-Cron oder Server-Cron).
 *
 * Steuert zeitbasierte Routinen wie die Auto-Archivierung veralteter Genehmigungen
 * und die regelmäßige Erstellung von Backups.
 *
 * Path: src/Infrastructure/Maintenance/CronScheduler.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class CronScheduler
{
    public function __construct(
        private BackupService $backupService,
        private ConfigInterface $config,
        private PermitService $permitService,
    ) {
    }

    /**
     * Wird asynchron (z.B. beim Laden des Admin-Dashboards) aufgerufen.
     * Prüft, ob seit dem letzten Lauf ausreichend Zeit (z.B. 24 Stunden) vergangen ist.
     *
     * (Der primäre Aufruf im Alltag)
     */
    public function runIfNeeded(): void
    {
        if (! $this->config->get('use_pseudo_cron', true)) {
            return;
        }

        // TODO Pfad und Dateiname in config/storage.php auslagern
        $logPath = \rtrim((string) $this->config->get('root_path'), '/\\') . '/storage/logs/last_cron_run.txt';
        $now     = \time();
        $lastRun = \file_exists($logPath) ? (int) \file_get_contents($logPath) : 0;

        if (($now - $lastRun) >= 86400) {
            // Sicherstellen, dass das logs/ Verzeichnis existiert, da file_put_contents
            // sonst fehlschlägt und der Cron bei JEDEM Seitenaufruf in eine Dauerschleife läuft.
            $logDir = \dirname($logPath);
            if (! \is_dir($logDir)) {
                @\mkdir($logDir, 0o755, true);
            }

            // Zeitstempel SOFORT schreiben, um parallele Überlappungen zu blockieren
            @\file_put_contents(
                $logPath,
                (string) $now,
                \LOCK_EX,
            );

            try {
                $this->runForce();
            } catch (\Throwable $t) {
                // Bei fatalem Abbruch den Zeitstempel zurücksetzen, damit der nächste Request es reparieren kann
                @\file_put_contents(
                    $logPath,
                    (string) $lastRun,
                    \LOCK_EX,
                );

                throw $t;
            }
        }
    }

    /**
     * Führt alle geplanten Jobs (Auto-Archivierung und Backups) sofort aus,
     * unabhängig davon, ob das Intervall bereits abgelaufen ist.
     *
     * (Der direkte Ausführungsbefehl)
     */
    public function runForce(): void
    {
        // 1. Auto-Archivierung
        $graceDays = (int) $this->config->get('archive_grace_days', 0);
        $this->permitService->autoArchiveExpiredPermits($graceDays);

        // 2. Auto-Backup prüfen (der BackupService hat intern noch eigene Timer-Logik)
        $this->backupService->checkAutoBackup();
    }
}
