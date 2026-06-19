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
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
        if (! $this->auth->hasPermission('dashboard.vouchers.remove')) {
            return 'Fehler: Keine Berechtigung zum Löschen von Gutscheinen.';
        }

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
