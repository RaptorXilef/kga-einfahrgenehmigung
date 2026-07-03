<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\PermitService;

/**
 * Action zum manuellen Markieren einer Genehmigung als 'bezahlt'.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('mark_as_paid')]
final readonly class PermitMarkAsPaidAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private PermitService $permitService,
        private SessionManager $sessionManager,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.finance.mark_paid';
    }

    /**
     * Markiert eine Genehmigung manuell als bezahlt im Storage.
     *
     * Nutzt PermitService::manualActivate().
     *
     * @return string Erfolgsmeldung oder leerer String bei Fehler.
     */
    public function execute(ServerRequest $request): mixed
    {
        $codes      = $request->post['codes'] ?? [];
        $singleCode = $request->post['code'] ?? '';

        if ($singleCode !== '') {
            $codes[] = $singleCode;
        }

        if (empty($codes)) {
            $this->sessionManager->addFlash('error', 'Fehler: Keine Genehmigungen ausgewählt.');

            return new RedirectResponse('admin.php');
        }

        $successCount = 0;
        $errorCount   = 0;

        foreach ($codes as $code) {
            if ($this->permitService->manualActivate($code)) {
                ++$successCount;
            } else {
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
            $this->sessionManager->addFlash('warning', "{$successCount} Zahlungen bestätigt, {$errorCount} fehlerhaft (evtl. bereits bezahlt).");
        } else {
            $this->sessionManager->addFlash('error', 'Fehler: Keine der gewählten Genehmigungen konnte aktualisiert werden.');
        }

        return new RedirectResponse('admin.php');
    }
}
