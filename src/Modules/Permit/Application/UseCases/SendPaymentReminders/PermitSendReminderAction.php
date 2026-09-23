<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SendPaymentReminders;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\System\Application\Services\AuditLoggerService;
use Exception;

#[Route('POST', '/send_reminder')]
final readonly class PermitSendReminderAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SendPaymentRemindersHandler $reminderHandler,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'finance.mark_paid';
    }

    public function execute(ServerRequest $request): mixed
    {
        $codes = $request->post['codes'] ?? [];
        $singleCode = $request->post['code'] ?? '';

        if ($singleCode !== '') {
            $codes[] = $singleCode;
        }

        if (empty($codes)) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Genehmigungen ausgewählt.');

            return new RedirectResponse('admin');
        }

        $successCount = 0;
        foreach ($codes as $code) {
            try {
                $this->reminderHandler->handle(new SendPaymentRemindersCommand($code, true));
                ++$successCount;
            } catch (Exception) {
                // Fehler beim individuellen Senden ignorieren
            }
        }

        if ($successCount > 0) {
            $this->auditLogger->log('PAYMENT_REMINDER', "{$successCount} Zahlungserinnerung(en) manuell versendet.");
            $this->sessionManager->addFlash('success', "{$successCount} Zahlungserinnerung(en) erfolgreich in die Warteschlange gelegt.");
        } else {
            $this->sessionManager->addFlash('warning', 'Keine Erinnerungen versendet (evtl. bereits bezahlt, Cooldown-Schutz aktiv oder gesperrt).');
        }

        return new RedirectResponse('admin');
    }
}
