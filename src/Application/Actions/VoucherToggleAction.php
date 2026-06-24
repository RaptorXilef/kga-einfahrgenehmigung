<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\VoucherToggleRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ActionInterface;
use App\Contracts\Application\RequiresPermissionInterface;
use App\Core\Service\VoucherService;

/**
 * Action zum Aktivieren oder Deaktivieren eines Gutscheins.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherToggleAction implements ActionInterface, RequiresPermissionInterface
{
    public function __construct(
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
            return new RedirectResponse('admin.php?msg=' . \urlencode($e->getMessage()));
        }
        $this->voucherService->toggleStatus($dto->code, $dto->targetStatus);
        $msg = 'Gutschein wurde ' . ($dto->targetStatus === 'aktiv' ? 'reaktiviert.' : 'gesperrt.');

        return new RedirectResponse('admin.php?msg=' . \urlencode($msg));
    }
}
