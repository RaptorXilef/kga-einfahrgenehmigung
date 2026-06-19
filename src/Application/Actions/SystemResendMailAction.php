<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Storage\JsonHelper;

/**
 * Action für den manuellen Neuversand von E-Mails aus den System-Logs.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SystemResendMailAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private MailLogInterface $mailLog,
        private MailServiceInterface $mailService,
    ) {
    }

    /**
     * Trigger für den Neuversand von E-Mails basierend auf den System-Logs.
     *
     * @param array<string, mixed> $post
     *
     * @return string Statusmeldung über den Erfolg des Neuversands.
     */
    public function execute(array $post): mixed
    {
        if (
            ! $this->auth->hasPermission('dashboard.logs.view')
            || ! $this->auth->hasPermission('dashboard.generator-tools.direct_issue.execute')
        ) {
            return 'Fehler: Keine Berechtigung zum aktiven Neuversand von E-Mails.';
        }

        try {
            $dto = SimpleIdentifierRequest::fromArray($post, 'timestamp');
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
