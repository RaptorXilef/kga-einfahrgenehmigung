<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\MarkPermitAsPaid;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\AuditLoggerInterface;
use DomainException;
use Override;

#[Route('GET', '/mark_as_paid')]
#[Route('POST', '/mark_as_paid')]
final readonly class PermitMarkAsPaidAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private SessionManager $sessionManager,
        private MarkPermitAsPaidHandler $markPaidHandler,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'finance.mark_paid';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
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
        $errorCount = 0;

        foreach ($codes as $code) {
            try {
                $command = new MarkPermitAsPaidCommand((string) $code, 'Manuell bestätigt');
                $this->markPaidHandler->handle($command);
                ++$successCount;
            } catch (DomainException) {
                ++$errorCount;
            }
        }

        if ($successCount === 1 && $errorCount === 0) {
            $this->auditLogger->log('PERMIT_PAID', "Zahlung für Vorgang '{$codes[0]}' bestätigt.");
            $this->sessionManager->addFlash('success', "Zahlung für Vorgang '{$codes[0]}' bestätigt.");
        } elseif ($successCount > 1 && $errorCount === 0) {
            $this->auditLogger->log('PERMIT_PAID', "Zahlung für {$successCount} Genehmigungen im Bulk-Verfahren bestätigt.");
            $this->sessionManager->addFlash('success', "Zahlung für {$successCount} Genehmigungen erfolgreich bestätigt.");
        } elseif ($successCount > 0 && $errorCount > 0) {
            $this->auditLogger->log('PERMIT_PAID', "Teilweiser Erfolg: {$successCount} Zahlungen bestätigt, {$errorCount} fehlerhaft.");
            $this->sessionManager->addFlash('warning', "{$successCount} Zahlungen bestätigt, {$errorCount} fehlerhaft (evtl. nicht gefunden).");
        } else {
            $this->sessionManager->addFlash('error', 'Fehler: Keine der gewählten Genehmigungen konnte aktualisiert werden.');
        }

        return new RedirectResponse('admin');
    }
}
