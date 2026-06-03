<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\PermitService;

// TODO DOCBLOCK
final readonly class CronScheduler
{
    public function __construct(
        private BackupService $backupService,
        private ConfigInterface $config,
        private PermitService $permitService,
    ) {
    }

    // TODO DOCBLOCK
    /**
     * Wird beim Laden des Admin-Dashboards aufgerufen.
     * Prüft, ob seit dem letzten Lauf 24 Stunden (86400 Sek) vergangen sind.
     */
    public function runIfNeeded(): void
    {
        if (! $this->config->get('use_pseudo_cron', true)) {
            return;
        }

        $logPath = \rtrim((string) $this->config->get('root_path'), '/\\') . '/storage/logs/last_cron_run.txt';
        $now     = \time();

        $lastRun = \file_exists($logPath) ? (int) \file_get_contents($logPath) : 0;

        if (($now - $lastRun) >= 86400) {
            $this->runForce();
            @\file_put_contents($logPath, (string) $now);
        }
    }

    // TODO DOCBLOCK
    /**
     * Führt alle geplanten Jobs sofort aus.
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
