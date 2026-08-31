<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\PermitService;

#[Route('POST', '/send_reminder')]
final readonly class PermitSendReminderAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private PermitService $permitService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        // Wir nutzen das ohnehin für Abrechnungen nötige Recht
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

            return new RedirectResponse('admin.php');
        }

        $successCount = 0;
        foreach ($codes as $code) {
            if ($this->permitService->dispatchReminder($code, true)) {
                ++$successCount;
            }
        }

        if ($successCount > 0) {
            $this->auditLogger->log('PAYMENT_REMINDER', "{$successCount} Zahlungserinnerung(en) manuell versendet.");
            $this->sessionManager->addFlash('success', "{$successCount} Zahlungserinnerung(en) erfolgreich in die Warteschlange gelegt.");
        } else {
            $this->sessionManager->addFlash('warning', 'Keine Erinnerungen versendet (evtl. bereits bezahlt, Cooldown-Schutz aktiv oder gesperrt).');
        }

        return new RedirectResponse('admin.php');
    }
}
