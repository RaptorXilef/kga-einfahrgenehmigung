<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\DeleteVoucher;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\System\Application\Services\AuditLoggerService;

/**
 * Action zum unwiderruflichen Löschen eines Gutscheins (VSA).
 */
#[Route('GET', '/delete_voucher')]
#[Route('POST', '/delete_voucher')]
final readonly class DeleteVoucherAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private SessionManager $sessionManager,
        private DeleteVoucherHandler $deleteHandler,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'vouchers.delete';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($request->post, 'code');
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin');
        }

        $command = new DeleteVoucherCommand($dto->identifier);
        $this->deleteHandler->handle($command);

        $this->auditLogger->log('VOUCHER_DELETE', "Gutscheincode '{$dto->identifier}' endgültig gelöscht.");
        $this->sessionManager->addFlash('success', "Gutschein '{$dto->identifier}' gelöscht.");

        return new RedirectResponse('admin');
    }
}
