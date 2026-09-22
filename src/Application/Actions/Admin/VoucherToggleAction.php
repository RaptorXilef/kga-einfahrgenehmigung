<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\VoucherToggleRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Voucher\Application\UseCases\ToggleVoucher\ToggleVoucherCommand;
use App\Modules\Voucher\Application\UseCases\ToggleVoucher\ToggleVoucherHandler;
use DomainException;
use Modules\System\Application\Services\AuditLoggerService;

#[Route('POST', '/activate_voucher')]
#[Route('POST', '/deactivate_voucher')]
#[RequiresAuth]
final readonly class VoucherToggleAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private ToggleVoucherHandler $toggleHandler,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'vouchers.suspend';
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

            return new RedirectResponse('admin');
        }

        try {
            $command = new ToggleVoucherCommand($dto->code, $dto->targetStatus);
            $this->toggleHandler->handle($command);

            $actionStr = $dto->targetStatus === 'aktiv' ? 'reaktiviert' : 'deaktiviert (gesperrt)';

            // LOG SCHREIBEN
            $this->auditLogger->log('VOUCHER_TOGGLE', "Gutscheincode '{$dto->code}' wurde {$actionStr}.");
            $this->sessionManager->addFlash('success', "Gutschein wurde {$actionStr}.");

        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return new RedirectResponse('admin');
    }
}
