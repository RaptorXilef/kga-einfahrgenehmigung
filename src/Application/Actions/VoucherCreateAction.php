<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\VoucherCreateRequest;
use App\Application\Exception\ValidationException;
use App\Application\Http\ServerRequest;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Core\Service\VoucherService;

/**
 * Action zum Erstellen eines neuen Gutscheins.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherCreateAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private VoucherService $voucherService,
    ) {
    }

    /**
     * Erstellt einen neuen Gutschein mit spezifischen Konditionen über VoucherService.
     *
     * Kontext: Beinhaltet Sicherheitsprüfung (hasPermission). Übergibt diverse Gutschein-Parameter.
     */
    public function execute(ServerRequest $request): mixed
    {
        try {
            $dto = VoucherCreateRequest::fromArray($request->post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        try {
            $code = $this->voucherService->createVoucher(
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

            return "Gutschein erstellt: <strong>$code</strong>";
        } catch (\Exception $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }
}
