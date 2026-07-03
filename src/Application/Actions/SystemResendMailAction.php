<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Service\AuditLoggerService;

/**
 * Action für den manuellen Neuversand von E-Mails aus den System-Logs.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('resend_mail')]
final readonly class SystemResendMailAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private MailLogInterface $mailLog,
        private MailServiceInterface $mailService,
        private SessionManager $sessionManager,
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
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php');
        }

        $logs = $this->mailLog->loadLogs();

        foreach ($logs as $log) {
            if ($log->timestamp->format('Y-m-d H:i:s') !== $dto->identifier) {
                continue;
            }

            if (empty($log->data)) {
                $this->sessionManager->addFlash('error', 'Fehler: Alter Log-Eintrag (Keine Rohdaten für Neuversand vorhanden).');

                return new RedirectResponse('admin.php');
            }

            $this->mailService->sendTemplate(
                $log->recipient,
                $log->subject,
                $log->template,
                $log->data,
            );

            $this->auditLogger->log('SYSTEM_MAIL_RESEND', "E-Mail '{$log->subject}' an {$log->recipient} manuell erneut versendet.");
            $this->sessionManager->addFlash('success', "E-Mail an {$log->recipient} wurde erfolgreich erneut versendet.");

            return new RedirectResponse('admin.php');
        }

        $this->sessionManager->addFlash('error', 'Fehler: Log-Eintrag nicht gefunden.');

        return new RedirectResponse('admin.php');
    }
}
