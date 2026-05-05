<?php

/**
 * API: Liefert den Preis für ein Template unter Berücksichtigung von Gutscheinen.
 *
 * Path: public/api/get_template_price.php
 */

declare(strict_types=1);

// Fehler für die API-Antwort unterdrücken, um JSON nicht zu korrumpieren
\ini_set('display_errors', '0');
\error_reporting(0);

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\PermitService;

\header('Content-Type: application/json');

try {
    // Hier zwei Ebenen hoch, da wir im Unterordner /api/ sind
    $container = require_once __DIR__ . '/../../src/Bootstrap/app.php';

    $permitService = $container->get(PermitService::class);
    $config        = $container->get(ConfigInterface::class);

    $key         = (string) ($_GET['key'] ?? 'std_7');
    $typ         = (string) ($_GET['typ'] ?? 'pkw');
    $voucherCode = \strtoupper(\trim((string) ($_GET['voucher'] ?? '')));

    $templates = $config->get('permit_templates', []);
    $template  = $templates[$key] ?? $templates['std_7'];

    $originalPrice = (float) ($template['prices'][$typ] ?? 0.0);
    $finalPrice    = $originalPrice;
    $discountText  = '';

    // Gutschein-Prüfung
    if ($voucherCode !== '') {
        $vouchers = $permitService->getVoucherService()->loadVouchers();
        if (isset($vouchers[$voucherCode])) {
            $v = $vouchers[$voucherCode];

            // Ablaufdatum prüfen
            $isExpired = false;
            if (! empty($v['expires_at'])) {
                $expiry = new \DateTimeImmutable($v['expires_at']);
                if ($expiry < new \DateTimeImmutable()) {
                    $isExpired = true;
                }
            }

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
        }
    }

    echo \json_encode([
        'success'      => true,
        'original'     => $originalPrice,
        'price'        => $finalPrice,
        'discountText' => $discountText,
        'formatted'    => \number_format($finalPrice, 2, ',', '.') . ' €',
        'isFree'       => $finalPrice <= 0,
    ]);

} catch (\Throwable $e) {
    // Falls ein schwerer Fehler auftritt, senden wir ihn als JSON
    echo \json_encode([
        'success'   => false,
        'error'     => $e->getMessage(),
        'price'     => 0.0,
        'formatted' => 'Fehler',
    ]);
}
