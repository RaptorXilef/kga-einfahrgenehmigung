<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\DeleteVoucher;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\System\AuditLoggerInterface;
use Override;

/**
 * Action zum unwiderruflichen Löschen eines Gutscheins (VSA).
 */
#[Route('POST', '/delete_voucher')]
#[RequiresAuth]
final readonly class DeleteVoucherAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
        private SessionManager $sessionManager,
        private DeleteVoucherHandler $deleteHandler,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'vouchers.delete';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        try {
            $dto = DeleteVoucherRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin');
        }

        $command = new DeleteVoucherCommand($dto->code);
        $this->deleteHandler->handle($command);

        $this->auditLogger->log('VOUCHER_DELETE', "Gutscheincode '{$dto->code}' endgültig gelöscht.");
        $this->sessionManager->addFlash('success', "Gutschein '{$dto->code}' gelöscht.");

        return new RedirectResponse('admin?focus=tab-vouchers');
    }
}
