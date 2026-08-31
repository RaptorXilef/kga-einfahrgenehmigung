<?php

declare(strict_types=1);

namespace App\Core\Service\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Core\Service\PermitService;

final readonly class CronScheduler
{
    public function __construct(
        private BackupServiceInterface $backupService,
        private ConfigInterface $config,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private PermitService $permitService,
    ) {
    }

    /**
     * Führt alle geplanten Jobs (Auto-Archivierung und Backups) sofort aus.
     * Wird in Kürze in einzelne Endpunkte aufgeteilt.
     */
    public function runForce(): void
    {
        // 1. Abgelaufene Genehmigungen archivieren
        $graceDays = (int) $this->config->get('archive_grace_days', 0);
        $this->permitService->autoArchiveExpiredPermits($graceDays);

        // 2. DSGVO Anonymisierung täglich ausführen (Einträge > 10 Jahre)
        $this->archiveRepository->anonymizeOldRecords(10);

        // 3. Zahlungserinnerungen für überfällige Permits versenden
        $this->permitService->sendPaymentReminders();

        // Wöchentlicher Sync der Spam-Domains
        $this->syncDisposableDomains();
    }

    /**
     * Aktualisiert die Anti-Spam-Liste automatisch einmal pro Woche von GitHub.
     */
    private function syncDisposableDomains(): void
    {
        $path = $this->config->getStoragePath('disposable_email.json');

        // Nur updaten, wenn die Datei älter als 7 Tage ist (604800 Sekunden)
        if (\file_exists($path) && (\time() - \filemtime($path)) < 604800) {
            return;
        }

        $url = 'https://raw.githubusercontent.com/eramitgupta/disposable-email/master/disposable_email.json';

        // Timeout auf 5 Sekunden setzen, damit der Cron bei GitHub-Problemen nicht blockiert
        $ctx = \stream_context_create(['http' => ['timeout' => 5]]);
        $json = @\file_get_contents($url, false, $ctx);

        // PHP 8.3+ natives json_validate schützt vor korrupten Downloads
        if ($json !== false && \json_validate($json)) {
            @\file_put_contents($path, $json, \LOCK_EX);
        }
    }
}
