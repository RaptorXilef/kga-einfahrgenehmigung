<?php

declare(strict_types=1);

namespace App\Core\Service\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\CronStateRepositoryInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Core\Service\PermitService;

/**
 * Scheduler für automatisierte Wartungsaufgaben (Pseudo-Cron oder Server-Cron).
 *
 * Steuert zeitbasierte Routinen wie die Auto-Archivierung veralteter Genehmigungen
 * und die regelmäßige Erstellung von Backups.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class CronScheduler
{
    public function __construct(
        private BackupServiceInterface $backupService,
        private ConfigInterface $config,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private PermitService $permitService,
        private LockManagerInterface $lockManager,
        private CronStateRepositoryInterface $cronState,
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

        $this->lockManager->executeWithLock('cron', function (): void {
            $now     = \time();
            $lastRun = $this->cronState->getLastRunTime();

            // TODO Zeitspanne für BAckup/Cronjob in config auslagern
            // FIX: 23 Stunden und 50 Minuten (85800 Sekunden) als Trigger.
            // Verhindert das Überspringen von Tagen durch leichte Zeitverschiebungen!
            if (($now - $lastRun) >= 85800) {
                $this->cronState->setLastRunTime($now);

                try {
                    $this->runForce();
                } catch (\Throwable $t) {
                    $this->cronState->setLastRunTime($lastRun);

                    throw $t;
                }
            }
        });
    }

    /**
     * Führt alle geplanten Jobs (Auto-Archivierung und Backups) sofort aus,
     * unabhängig davon, ob das Intervall bereits abgelaufen ist.
     *
     * (Der direkte Ausführungsbefehl)
     */
    public function runForce(): void
    {
        // 1. Abgelaufene Genehmigungen archivieren
        $graceDays = (int) $this->config->get('archive_grace_days', 0);
        $this->permitService->autoArchiveExpiredPermits($graceDays);

        // 2. DSGVO Anonymisierung täglich ausführen (Einträge > 10 Jahre)
        $this->archiveRepository->anonymizeOldRecords(10);

        // 3. Auto-Backups prüfen und ggf. rotieren
        $this->backupService->checkAutoBackup();
    }
}
