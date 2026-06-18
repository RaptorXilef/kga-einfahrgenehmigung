<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\SimpleIdentifierRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Core\Service\VoucherService;

/**
 * Action zum Aktivieren oder Deaktivieren eines Gutscheins.
 *
 * Path: src/Application/Actions/VoucherToggleAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VoucherToggleAction implements ActionInterface
{
    public function __construct(
        private AuthService $auth,
        private VoucherService $voucherService,
    ) {
    }

    /**
     * Setzt den Sperrstatus einer bestehenden Genehmigung.
     *
     * @param array<string, mixed> $post
     *
     * @return string Statusänderungs-Meldung.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.vouchers.suspend')) {
            return 'Fehler: Keine Berechtigung für diese Aktion.';
        }

        try {
            $dto = SimpleIdentifierRequest::fromArray($post, 'code');
        } catch (ValidationException $e) {
            return $e->getMessage();
        }

        $action = $post['action'] ?? '';
        $status = $action === 'activate_voucher' ? 'aktiv' : 'deaktiviert';

        $this->voucherService->toggleStatus($dto->identifier, $status);

        return 'Gutschein wurde ' . ($status === 'aktiv' ? 'reaktiviert.' : 'gesperrt.');
    }
}
