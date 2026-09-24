<?php

declare(strict_types=1);

namespace App\Modules\Voucher\Application\UseCases\CreateVoucher;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\System\Application\Services\AuditLoggerService;
use Override;

/**
 * Action zum Erstellen eines neuen Gutscheins (VSA).
 */
#[Route('GET', '/create_voucher')]
#[Route('POST', '/create_voucher')]
final readonly class CreateVoucherAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private AuditLoggerService $auditLogger,
        private AuthService $auth,
        private ConfigInterface $config,
        private SessionManager $sessionManager,
        private CreateVoucherHandler $createHandler,
    ) {
    }

    #[Override]
    public function getRequiredPermission(): string
    {
        return 'vouchers.create';
    }

    #[Override]
    public function execute(ServerRequest $request): ResponseInterface
    {
        if ($request->getMethod() === 'GET') {
            return new RedirectResponse('admin?focus=tab-tools');
        }

        $maxPlot = $this->config->getInt('max_plot_number', 9999);
        $dto = VoucherCreateRequest::fromArray($request->post, $maxPlot);

        $command = new CreateVoucherCommand(
            $dto->reason,
            $this->auth->getUserId(),
            $dto->templateKey,
            $dto->prefillData,
            $dto->type,
            $dto->value,
            $dto->isMultiUse,
            $dto->maxUses,
            $dto->customCode,
            $dto->expiresAt,
            $dto->dateMode,
        );

        $this->createHandler->handle($command);

        $this->auditLogger->log('VOUCHER_CREATE', "Gutscheincode verarbeitet. Grund/Notiz: {$dto->reason}");
        $this->sessionManager->addFlash('success', 'Gutschein wurde erfolgreich generiert!');

        return new RedirectResponse('admin?focus=tab-vouchers');
    }
}
