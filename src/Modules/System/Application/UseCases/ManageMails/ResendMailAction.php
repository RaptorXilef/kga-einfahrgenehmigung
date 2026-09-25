<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageMails;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\System\AuditLoggerInterface;
use Override;

#[Route('GET', '/resend_mail')]
#[Route('POST', '/resend_mail')]
final readonly class ResendMailAction implements ActionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private MailLogInterface $mailLog,
        private MailServiceInterface $mailService,
        private SessionManager $sessionManager,
    ) {
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = ResendMailRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin');
        }

        $logs = $this->mailLog->loadLogs();

        foreach ($logs as $log) {
            if ($log->timestamp->format('Y-m-d H:i:s') !== $dto->timestamp) {
                continue;
            }

            if ($log->data === []) {
                $this->sessionManager->addFlash('error', 'Fehler: Alter Log-Eintrag (Keine Rohdaten für Neuversand vorhanden).');

                return new RedirectResponse('admin');
            }

            $this->mailService->sendTemplate(
                $log->recipient,
                $log->subject,
                $log->template->value,
                $log->data,
                $log->replyTo,
                100, // Manuell angestoßene Mails sollten sofort rausgehen
            );

            $this->auditLogger->log('SYSTEM_MAIL_RESEND', "E-Mail '{$log->subject}' an {$log->recipient} manuell erneut versendet.");
            $this->sessionManager->addFlash('success', "E-Mail an {$log->recipient} wurde erfolgreich erneut versendet.");

            return new RedirectResponse('admin');
        }

        $this->sessionManager->addFlash('error', 'Fehler: Log-Eintrag nicht gefunden.');

        return new RedirectResponse('admin');
    }
}
