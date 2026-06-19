<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\VoucherCreateRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\VoucherService;

/**
 * Action zum Erstellen eines neuen Gutscheins.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherCreateAction implements ActionInterface
{
    public function __construct(
        private VoucherService $voucherService,
    ) {
    }

    /**
     * Erstellt einen neuen Gutschein mit spezifischen Konditionen über VoucherService.
     *
     * Kontext: Beinhaltet Sicherheitsprüfung (hasPermission). Übergibt diverse Gutschein-Parameter.
     *
     * @param array<string, mixed> $post
     *
     * @return string Bestätigung mit dem generierten Gutscheincode.
     */
    public function execute(array $post): mixed
    {
        try {
            $dto = VoucherCreateRequest::fromArray($post);
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        try {
            $code = $this->voucherService->createVoucher(
                $dto->reason,
                (string) ($_SESSION['user_id'] ?? 'sys_admin'),
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
