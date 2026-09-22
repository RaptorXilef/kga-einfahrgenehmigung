<?php

declare(strict_types=1);

namespace App\Application\Actions\Admin;

use App\Application\Attribute\Route;
use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Modules\Voucher\Application\UseCases\DeleteVoucher\DeleteVoucherCommand;
use App\Modules\Voucher\Application\UseCases\DeleteVoucher\DeleteVoucherHandler;
use Modules\System\Application\Services\AuditLoggerService;

/**
 * Action zum unwiderruflichen Löschen eines Gutscheins.
 */
#[Route('GET', '/delete_voucher')]
#[Route('POST', '/delete_voucher')]
final readonly class VoucherDeleteAction implements ActionInterface, RequiresPermissionInterface
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
);
            $username = isset($users[$dto->userId]) ? $users[$dto->userId]->username : 'Unbekannt';

            $this->changePasswordHandler->handle(new ChangeUserPasswordCommand($dto->userId, $dto->newPassword));

            $this->auditLogger->log('USER_RESET_PASSWORD', "Kennwort für Benutzer '{$username}' (ID: {$dto->userId}) manuell zurückgesetzt.");
            $this->sessionManager->addFlash('success', 'Passwort wurde zurückgesetzt.');

            return new RedirectResponse('users');
        } catch (DomainException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('users');
        }
    }
}
