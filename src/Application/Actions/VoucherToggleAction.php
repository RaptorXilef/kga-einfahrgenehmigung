<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\VoucherToggleRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Core\Service\AuditLoggerService;
use App\Core\Service\VoucherService;

/**
 * Action zum Aktivieren oder Deaktivieren eines Gutscheins.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('activate_voucher')]
#[ActionRoute('deactivate_voucher')]
final readonly class VoucherToggleAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private VoucherService $voucherService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.vouchers.suspend';
    }

    /**
     * Setzt den Sperrstatus einer bestehenden Genehmigung.
     *
     * @return string Statusänderungs-Meldung.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = VoucherToggleRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php');
        }

        $this->voucherService->toggleStatus($dto->code, $dto->targetStatus);

        $actionStr = $dto->targetStatus === 'aktiv' ? 'reaktiviert' : 'deaktiviert (gesperrt)';

        // LOG SCHREIBEN
        $this->auditLogger->log('VOUCHER_TOGGLE', "Gutscheincode '{$dto->code}' wurde {$actionStr}.");

        $msg = 'Gutschein wurde ' . ($dto->targetStatus === 'aktiv' ? 'reaktiviert.' : 'gesperrt.');
        $this->sessionManager->addFlash('success', $msg);

        return new RedirectResponse('admin.php');
    }
}
