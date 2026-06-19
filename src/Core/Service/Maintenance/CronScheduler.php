<?php

declare(strict_types=1);

namespace App\Core\Service\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Core\Service\PermitService;
use App\Infrastructure\Storage\SafeJsonWriterTrait;

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
    use SafeJsonWriterTrait;

    public function __construct(
        private BackupServiceInterface $backupService,
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
        $logPath  = $this->config->getStoragePath('logs/last_cron_run.txt');
        $lockPath = $this->config->getStoragePath('logs/cron.lock');
        $now      = \time();

        // FIX: Atomarer, nicht-blockierender Lock (Verhindert mehrfache parallele Backups)
        $fp = @\fopen($lockPath, 'c');
        if ($fp && \flock($fp, \LOCK_EX | \LOCK_NB)) {

            // Sicherstellen, dass das logs/ Verzeichnis existiert, da file_put_contents
            // sonst fehlschlägt und der Cron bei JEDEM Seitenaufruf in eine Dauerschleife läuft.
            $logDir = \dirname($logPath);
            if (! \is_dir($logDir)) {
                @\mkdir($logDir, 0o755, true);
            }

            $lastRun = \file_exists($logPath) ? (int) \file_get_contents($logPath) : 0;

            if (($now - $lastRun) >= 86400) {

                // Zeitstempel SOFORT schreiben, um parallele Überlappungen zu blockieren
                $result = @\file_put_contents(
                    $logPath,
                    (string) $now,
                    \LOCK_EX,
                );
                if ($result === false) {
                    throw new \RuntimeException('Kritischer Schreibfehler: Cron-Log konnte nicht geschrieben werden.');
                }

                try {
                    $this->runForce();
                } catch (\Throwable $t) {
                    // Bei fatalem Abbruch den Zeitstempel zurücksetzen, damit der nächste Request es reparieren kann
                    $result = @\file_put_contents(
                        $logPath,
                        (string) $lastRun,
                        \LOCK_EX,
                    );
                    if ($result === false) {
                        throw new \RuntimeException('Kritischer Schreibfehler: Cron-Log konnte nicht geschrieben werden.');
                    }

                    throw $t;
                }
            }
            \flock($fp, \LOCK_UN);
            \fclose($fp);
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
