<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ActionInterface;
use App\Core\Service\AuthService;
use App\Core\Service\VoucherService;

/**
 * Action zum Erstellen eines neuen Gutscheins.
 *
 * Path: src/Application/Actions/VoucherCreateAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
     *
     * @param array<string, mixed> $post
     *
     * @return string Bestätigung mit dem generierten Gutscheincode.
     */
    public function execute(array $post): string
    {
        if (! $this->auth->hasPermission('dashboard.generator-tools.voucher_gen.execute')) {
            return 'Fehler: Sie haben keine Berechtigung, Gutscheine zu erstellen.';
        }

        $tplKey = (string) ($post['template_key'] ?? 'std.7');

        if (! $this->auth->hasPermission("template.$tplKey")) {
            return "Fehler: Sie haben keine Berechtigung, den Typ '$tplKey' zu verwenden.";
        }

        try {
            $reason     = (string) ($post['reason'] ?? 'Gutschein');
            $type       = (string) ($post['voucher_discount_type'] ?? 'free');
            $val        = (float) ($post['voucher_discount_value'] ?? 0.0);
            $multi      = isset($post['voucher_multi_use']);
            $max_uses   = $multi ? (int) ($post['voucher_max_uses'] ?? 1) : 1;
            $custom     = (string) ($post['voucher_custom_code'] ?? '');
            $expires_at = (string) ($post['voucher_expires_at'] ?? '');
            $date_mode  = (string) ($post['voucher_date_mode'] ?? 'fixed');

            $preData = [
                'datum_bis'   => $date_mode === 'fixed' ? (string) ($post['datum_bis'] ?? '') : '',
                'datum_von'   => $date_mode === 'fixed' ? (string) ($post['datum_von'] ?? '') : '',
                'firma'       => \trim(\strip_tags((string) ($post['firma'] ?? ''))),
                'kennzeichen' => \trim(\strip_tags((string) ($post['kennzeichen'] ?? ''))),
                'name'        => \trim(\strip_tags((string) ($post['name'] ?? ''))),
                'parzelle'    => \trim(\strip_tags((string) ($post['parzelle'] ?? ''))),
                'typ'         => (string) ($post['typ'] ?? ''),
                'zweck'       => \strip_tags((string) ($post['zweck'] ?? '')),
            ];

            $code = $this->voucherService->createVoucher(
                $reason,
                (string) ($_SESSION['user_id'] ?? 'sys_admin'),
                $tplKey,
                $preData,
                $type,
                $val,
                $multi,
                $max_uses,
                $custom,
                $expires_at ?: null,
                $date_mode,
            );

            return "Gutschein erstellt: <strong>$code</strong>";
        } catch (\Exception $e) {
            return 'Fehler: ' . $e->getMessage();
        }
    }
}
