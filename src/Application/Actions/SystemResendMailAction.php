<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;

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
            return new RedirectResponse('admin.php?msg=' . \urlencode($e->getMessage()));
        }
        $logs = $this->mailLog->loadLogs();
        foreach ($logs as $log) {
            if ($log->timestamp->format('Y-m-d H:i:s') !== $dto->identifier) {
                continue;
            }
            if (empty($log->data)) {
                return new RedirectResponse('admin.php?msg=' . \urlencode('Fehler: Alter Log-Eintrag (Keine Rohdaten für Neuversand vorhanden).'));
            }
            $this->mailService->sendTemplate($log->recipient, $log->subject, $log->template, $log->data);

            return new RedirectResponse('admin.php?msg=' . \urlencode("E-Mail an {$log->recipient} wurde erfolgreich erneut versendet."));
        }

        return new RedirectResponse('admin.php?msg=' . \urlencode('Fehler: Log-Eintrag nicht gefunden.'));
    }
}
