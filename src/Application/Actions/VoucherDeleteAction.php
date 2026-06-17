<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Core\Service\VoucherService;

/**
 * Action zum unwiderruflichen Löschen eines Gutscheins.
 *
 * Path: src/Application/Actions/VoucherDeleteAction.php
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
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.vouchers.remove')) {
            return 'Fehler: Keine Berechtigung zum Löschen von Gutscheinen.';
        }

        $code = (string) ($post['code'] ?? '');

        return $this->voucherService->deleteVoucher($code)
            ? "Gutschein '$code' wurde unwiderruflich gelöscht."
            : 'Fehler: Gutschein nicht gefunden.';
    }
}
