<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Contracts\Mail\MailServiceInterface;
use App\Modules\System\Domain\MailJob;
use App\Modules\System\Domain\MailQueueRepositoryInterface;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;
use Exception;

/**
 * Service für die asynchrone E-Mail-Verarbeitung über eine Warteschlange.
 * Speichert ausgehende E-Mails zunächst im Repository und verarbeitet sie gestaffelt nach Priorität.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MailQueueService implements MailServiceInterface
{
    public function __construct(
        private MailQueueRepositoryInterface $repository,
        private MailServiceInterface $realMailService,
    ) {
    }

    // --- Queue Lifecycle Core ---
    /**
     * Schritt 1: Mail in die Warteschlange einreihen
     *
     * Reiht eine neue E-Mail mit der definierten Priorität in die Warteschlange ein.
     *
     * @param string $recipient Die E-Mail-Adresse des Empfängers.
     * @param string $subject Der Betreff der E-Mail.
     * @param string $template Der Schlüssel/Name des zu verwendenden E-Mail-Templates.
     * @param array<string, mixed> $data Die dynamischen Daten für das Template.
     * @param string|null $replyTo Optionale Antwort-Adresse.
     * @param int $priority Wichtigkeit des Jobs.
     *
     * @return bool True bei erfolgreicher Einreihung.
     */
    public function sendTemplate(string $recipient, string $subject, string $template, array $data, ?string $replyTo = null, int $priority = 50, array $attachments = []): bool
    {
        // Anhänge Base64 encodiert im JSON Payload der Queue speichern
        if (!empty($attachments)) {
            $safeAtt = [];
            foreach ($attachments as $att) {
                $safeAtt[] = [
                    'name' => $att['name'],
                    'mime' => $att['mime'] ?? 'application/pdf',
                    'content_base64' => \base64_encode($att['content']),
                ];
            }
            $data['_attachments'] = $safeAtt;
        }

        $job = new MailJob(
            \uniqid('mq_'),
            $recipient,
            $replyTo,
            $subject,
            new TemplateKey($template),
            $data,
            0,
            new DateTimeImmutable(),
            $priority,
        );
        $this->repository->enqueue($job);

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
        return $this->repository->processBatch($limit, function (string $rec, string $sub, string $tpl, array $dat, ?string $replyTo): void {

            // Anhänge aus der Datenbank wiederherstellen
            $attachments = [];
            if (!empty($dat['_attachments'])) {
                foreach ($dat['_attachments'] as $att) {
                    $attachments[] = [
                        'name' => $att['name'],
                        'mime' => $att['mime'],
                        'content' => \base64_decode($att['content_base64'], true),
                    ];
                }
                unset($dat['_attachments']);
            }

            $result = $this->realMailService->sendTemplate($rec, $sub, $tpl, $dat, $replyTo, 50, $attachments); // SMTPService ignoriert priority

            if ($result !== true) {
                throw new Exception((string) $result);
            }
        });
    }
}
