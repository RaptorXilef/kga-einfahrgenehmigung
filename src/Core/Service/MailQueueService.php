<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Storage\MailQueueRepositoryInterface;

/**
 * TODO Phase 3 Bearbeitet
 * Service zur zeitversetzten E-Mail-Verarbeitung via Queueing.
 *
 * Abstrahiert und entkoppelt den physischen SMTP-Verbindungsprozess von Web-Requests.
 * Speichert ausgehende E-Mails als JSON-Spool oder DB-Queue und wickelt den Versand blockweise ab.
 * Kontext: Performance- und Ausfallsicherheits-Layer für den E-Mail-Subversand.
 *
 * Verwaltet den E-Mail-Versand über eine Queue, um Systemressourcen zu schonen.
 * Unterstützt die Speicherung von E-Mails in einer MySQL-Datenbank oder in einer JSON-Datei,
 * bevor sie durch den eigentlichen SMTP-Service verarbeitet werden.
 *
 * Path: src/Core/Service/MailQueueService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class MailQueueService implements MailServiceInterface
{
    public function __construct(
        private MailQueueRepositoryInterface $repository,
        private MailServiceInterface $realMailService, // Der echte SMTP-Service
    ) {
    }

    // TODO DocBlock
    public function sendTemplate(string $recipient, string $subject, string $template, array $data): bool|string
    {
        $this->repository->enqueue($recipient, $subject, $template, $data);

        return true;
    }

    // TODO DocBlock
    public function processQueue(int $limit = 5): int
    {
        // Wir übergeben eine Closure an das Repository, die den echten Mailversand triggert.
        return $this->repository->processBatch($limit, function (string $rec, string $sub, string $tpl, array $dat): void {
            $result = $this->realMailService->sendTemplate($rec, $sub, $tpl, $dat);
            if ($result !== true) {
                throw new \Exception((string) $result);
            }
        });
    }

    /**
     * Delegiert den Lesezugriff auf historische Versand-Logs an den zugrundeliegenden SMTP-Service.
     *
     * @return array<int, array<string, mixed>> Array mit Log-Einträgen.
     */
    public function loadLogs(): array
    {
        return $this->realMailService->loadLogs();
    }

    /**
     * Speichert Protokolle für den E-Mail-Versand.
     * Delegiert den Schreibzugriff für Log-Dateien an den SMTP-Dienst weiter.
     *
     * @param array<int, array<string, mixed>> $logs Liste der zu speichernden Log-Einträge.
     */
    public function saveLogs(array $logs, bool $forceSql = false): void
    {
        // Die Queue selbst speichert keine Logs, sie leitet den Befehl
        // an den echten Mail-Service (SmtpMailService) weiter.
        $this->realMailService->saveLogs($logs, $forceSql);
    }
}
