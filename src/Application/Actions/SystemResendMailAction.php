<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Infrastructure\Storage\JsonHelper;

/**
 * Action für den manuellen Neuversand von E-Mails aus den System-Logs.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemResendMailAction implements ActionInterface
{
    public function __construct(
        private MailLogInterface $mailLog,
        private MailServiceInterface $mailService,
    ) {
    }

    /**
     * Trigger für den Neuversand von E-Mails basierend auf den System-Logs.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($request->post, 'timestamp');
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        $logs = $this->mailLog->loadLogs();

        foreach ($logs as $log) {
            if (! (($log['timestamp'] ?? '') === $dto->identifier)) {
                continue;
            }

            $payload = $log['data'] ?? [];

            // Wenn aus MySQL geladen, ist data ein JSON-String und muss decodiert werden
            if (\is_string($payload)) {
                $payload = JsonHelper::decode($payload);
            }

            if (empty($payload)) {
                return 'Fehler: Alter Log-Eintrag (Keine Rohdaten für Neuversand vorhanden).';
            }

            // Erneuter Versand
            $this->mailService->sendTemplate(
                $log['recipient'],
                $log['subject'],
                $log['template'],
                $payload,
            );

            return "E-Mail an {$log['recipient']} wurde erfolgreich erneut versendet.";
        }

        return 'Fehler: Log-Eintrag nicht gefunden.';
    }
}
