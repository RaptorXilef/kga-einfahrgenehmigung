<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Storage\MailQueueRepositoryInterface;

/**
 * Service für die asynchrone E-Mail-Verarbeitung über eine Warteschlange.
 * Speichert ausgehende E-Mails zunächst im Repository und verarbeitet sie gestaffelt.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MailQueueService implements MailServiceInterface
{
    public function __construct(
        private MailQueueRepositoryInterface $repository,
        private MailServiceInterface $realMailService, // Der echte SMTP-Service
    ) {
    }

    // --- Queue Lifecycle Core ---

    /**
     * Schritt 1: Mail in die Warteschlange einreihen
     *
     * Reiht eine neue E-Mail in die Warteschlange ein.
     *
     * @param string               $recipient Die E-Mail-Adresse des Empfängers.
     * @param string               $subject   Der Betreff der E-Mail.
     * @param string               $template  Der Schlüssel/Name des zu verwendenden E-Mail-Templates.
     * @param array<string, mixed> $data      Die dynamischen Daten für das Template.
     *
     * @return bool|string True bei erfolgreicher Einreihung.
     */
    public function sendTemplate(string $recipient, string $subject, string $template, array $data): bool|string
    {
        $this->repository->enqueue($recipient, $subject, $template, $data);

        return true;
    }

    /**
     * Schritt 2: Warteschlange abarbeiten und SMTP-Versand triggern
     *
     * Verarbeitet einen Stapel von E-Mails aus der Warteschlange und versendet diese.
     *
     * @param int $limit Maximale Anzahl der zu verarbeitenden E-Mails in diesem Durchlauf.
     *
     * @return int Die Anzahl der erfolgreich verarbeiteten und versendeten E-Mails.
     */
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
}
