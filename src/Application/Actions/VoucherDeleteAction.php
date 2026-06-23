<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Core\Service\VoucherService;

/**
 * Action zum unwiderruflichen Löschen eines Gutscheins.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherDeleteAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
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
            return $e->getMessage();
        }

        return $this->voucherService->deleteVoucher($dto->identifier)
            ? "Gutschein '{$dto->identifier}' gelöscht."
            : "Fehler: Gutschein '{$dto->identifier}' nicht gefunden.";
    }
}
