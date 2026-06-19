<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Core\Service\VoucherService;

/**
 * Action zum unwiderruflichen Löschen eines Gutscheins.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class VoucherDeleteAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private VoucherService $voucherService,
    ) {
    }

    /**
     * TODO DOCBLOCK
     * Löscht einen Gutschein unwiderruflich.
     */
    public function execute(array $post): mixed
    {
        try {
            $dto = SimpleIdentifierRequest::fromArray($post, 'code');
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        return $this->voucherService->deleteVoucher($dto->identifier)
            ? "Gutschein '{$dto->identifier}' gelöscht."
            : "Fehler: Gutschein '{$dto->identifier}' nicht gefunden.";
    }
}
