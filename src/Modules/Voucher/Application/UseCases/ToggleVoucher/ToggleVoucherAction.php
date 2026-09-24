<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\ToggleVoucher;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\System\Application\Services\AuditLoggerService;
use DomainException;
use Override;

#[Route('POST', '/activate_voucher')]
#[Route('POST', '/deactivate_voucher')]
#[RequiresAuth]
final readonly class ToggleVoucherAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private ToggleVoucherHandler $toggleHandler,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'vouchers.suspend';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
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

            $this->auditLogger->log('VOUCHER_TOGGLE', "Gutscheincode '{$dto->code}' wurde {$actionStr}.");
            $this->sessionManager->addFlash('success', "Gutschein wurde {$actionStr}.");
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());
        }

        return new RedirectResponse('admin');
    }
}
