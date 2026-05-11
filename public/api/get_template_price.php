<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * API: Liefert den Preis für ein Template unter Berücksichtigung von Gutscheinen.
 *
 * Path: public/api/get_template_price.php
 */

declare(strict_types=1);

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\PermitService;

\header('Content-Type: application/json');

try {
    // Hier zwei Ebenen hoch, da wir im Unterordner /api/ sind
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';

    $permitService = $container->get(PermitService::class);
    $config        = $container->get(ConfigInterface::class);

    $vehicleTypes = $config->get('vehicle_types', []);
    $defaultType  = ! empty($vehicleTypes) ? \array_key_first($vehicleTypes) : 'pkw';

    $key         = (string) ($_GET['key'] ?? 'std_7');
    $typ         = (string) ($_GET['typ'] ?? $defaultType); // Dynamischer Fallback (pkw)
    $voucherCode = \strtoupper(\trim((string) ($_GET['voucher'] ?? '')));

    $templates = $config->get('permit_templates', []);
    $template  = $templates[$key] ?? $templates['std_7'];

    $originalPrice = (float) ($template['prices'][$typ] ?? 0.0);
    $finalPrice    = $originalPrice;
    $discountText  = '';

    // Gutschein-Prüfung
    if ($voucherCode !== '') {
        // Wir lassen den Service die Arbeit machen!
        $vouchers = $permitService->getVoucherService()->loadVouchers();
        $v        = $vouchers[$voucherCode] ?? null;

        if ($v) {
            // Check gegen Ablaufdatum (Logik bleibt hier, bis wir eine isValid() Methode im Service haben)
            $isExpired = ! empty($v['expires_at'])
                && new \DateTimeImmutable($v['expires_at']) < new \DateTimeImmutable();

            if (! $isExpired) {
                // Preis berechnen über die neue Methode im PermitService
                $finalPrice   = $permitService->calculateDiscountedPrice($originalPrice, $v);
                $discountText = match ($v['type']) {
                    'free'    => '100% Rabatt (Kostenlos)',
                    'percent' => (float) $v['value'] . '% Rabatt',
                    'fixed'   => 'Sonderpreis aktiviert',
                    default   => ''
                };
            } else {
                $discountText = 'Code abgelaufen';
            }
        } else {
            $discountText = 'Ungültiger Code';
        }
    }

    echo \json_encode([
        'success'      => true,
        'original'     => $originalPrice,
        'price'        => $finalPrice,
        'discountText' => $discountText,
        'formatted'    => \number_format($finalPrice, 2, ',', '.') . ' €',
        'isFree'       => $finalPrice <= 0,
    ], \JSON_UNESCAPED_UNICODE); // Unicode für das € Zeichen

} catch (\Throwable $e) {
    // Falls ein schwerer Fehler auftritt, senden wir ihn als JSON
    echo \json_encode([
        'success'   => false,
        'error'     => $e->getMessage(),
        'price'     => 0.0,
        'formatted' => 'Fehler',
    ]);
}
