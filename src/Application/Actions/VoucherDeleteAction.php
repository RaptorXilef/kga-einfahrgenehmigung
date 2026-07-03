<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Attribute\ActionRoute;
use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Core\Service\VoucherService;

/**
 * Action zum unwiderruflichen Löschen eines Gutscheins.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
#[ActionRoute('delete_voucher')]
final readonly class VoucherDeleteAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
        private SessionManager $sessionManager,
        private VoucherService $voucherService,
    ) {
    }

    public function getRequiredPermission(): string
    {
        return 'dashboard.vouchers.remove';
    }

    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($request->post, 'code');
        } catch (ValidationException $e) {
            $this->sessionManager->addFlash('error', $e->getMessage());

            return new RedirectResponse('admin.php');
        }

        if ($this->voucherService->deleteVoucher($dto->identifier)) {
            $this->sessionManager->addFlash('success', "Gutschein '{$dto->identifier}' gelöscht.");
        } else {
            $this->sessionManager->addFlash('error', "Fehler: Gutschein '{$dto->identifier}' nicht gefunden.");
        }

        return new RedirectResponse('admin.php');
    }
}
