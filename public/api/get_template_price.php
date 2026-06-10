<?php

/**
 * API: Liefert den Preis für ein Template unter Berücksichtigung von Gutscheinen.
 *
 * Path: public/api/get_template_price.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */

declare(strict_types=1);

use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Service\PermitService;

try {
    // Hier zwei Ebenen hoch, da wir im Unterordner /api/ sind
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';
    JsonResponse::enforceCsrfProtection();

    // SICHERHEIT: Nur POST erlauben
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        JsonResponse::error('Methode nicht erlaubt.', 405);
    }

    // Daten aus dem JSON-Body (POST-Stream) lesen
    $input         = \json_decode(\file_get_contents('php://input'), true) ?? [];
    $config        = $container->get(ConfigInterface::class);
    $permitService = $container->get(PermitService::class);
    $vehicleTypes  = $config->get('vehicle_types', []);
    $defaultType   = empty($vehicleTypes) ? 'pkw' : \array_key_first($vehicleTypes);
    $key           = (string) ($input['key'] ?? 'std_7');
    $typ           = (string) ($input['typ'] ?? $defaultType); // Dynamischer Fallback (pkw)
    $voucherCode   = \strtoupper(\trim((string) ($input['voucher'] ?? '')));
    $templates     = $config->get('permit_templates', []);
    $template      = $templates[$key] ?? $templates['std_7'];
    $originalPrice = (float) ($template['prices'][$typ] ?? 0.0);
    $finalPrice    = $originalPrice;
    $discountText  = '';

    // Gutschein-Prüfung
    if ($voucherCode !== '') {
        // Wir lassen den Service die Arbeit machen!
        $voucherRepo    = $container->get(VoucherRepositoryInterface::class);
        $voucherService = $permitService->getVoucherService();

        $vouchers = $voucherRepo->loadAll();
        $v        = $vouchers[$voucherCode] ?? null;

        if ($v) {
            // NUTZUNG DER isValid() SERVICE-METHODE
            if ($voucherService->isValid($v)) {
                // Preis berechnen über die entsprechende Methode im PermitService
                $finalPrice = $permitService->calculateDiscountedPrice($originalPrice, $v);
                // [x] sortiert
                $discountText = match ($v['type']) {
                    'fixed'   => 'Sonderpreis aktiviert',
                    'free'    => '100% Rabatt (Kostenlos)',
                    'percent' => (float) $v['value'] . '% Rabatt',
                    default   => ''
                };
            } else {
                // Differenzierteres Feedback für den User
                $isDeactivated = ($v['status'] ?? 'aktiv') === 'deaktiviert';
                $discountText  = $isDeactivated ? 'Code gesperrt' : 'Code abgelaufen';
            }
        } else {
            $discountText = 'Ungültiger Code';
        }
    }

    // [x] sortiert
    JsonResponse::success([
        'discountText' => $discountText,
        'formatted'    => \number_format($finalPrice, 2, ',', '.') . ' €',
        'isFree'       => $finalPrice <= 0,
        'original'     => $originalPrice,
        'price'        => $finalPrice,
    ]);
} catch (\Throwable $e) {
    // Bei diesem spezifischen Endpoint wollen wir auch im Fehlerfall eine formatierte Antwort
    // [x] sortiert
    JsonResponse::send([
        'error'     => $e->getMessage(),
        'formatted' => 'Fehler',
        'price'     => 0.0,
        'success'   => false,
    ], 400);
}
