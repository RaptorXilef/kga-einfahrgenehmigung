<?php

/**
 * API: Liefert den Preis für ein Template unter Berücksichtigung von Gutscheinen.
 * Path: public/api/get_template_price.php
 */

declare(strict_types=1);

// Fehler für die API-Antwort unterdrücken, um JSON nicht zu korrumpieren
\ini_set('display_errors', '0');
\error_reporting(0);

$appRoot = (function (): string {
    $dir = __DIR__;
    while ($dir !== \dirname($dir)) {
        if (\file_exists($dir . '/vendor/autoload.php')) {
            return $dir;
        }
        $dir = \dirname($dir);
    }

    return \dirname(__DIR__, 2);
})();

require_once $appRoot . '/vendor/autoload.php';

use App\Bootstrap\Container;
use App\Core\Service\PermitService;
use App\Infrastructure\Config\Config;

\header('Content-Type: application/json');

try {
    $key         = (string) ($_GET['key'] ?? 'std_7');
    $typ         = (string) ($_GET['typ'] ?? 'pkw');
    $voucherCode = \strtoupper(\trim((string) ($_GET['voucher'] ?? '')));

    $settings              = require $appRoot . '/config/config.php';
    $settings['root_path'] = $appRoot;
    $config                = new Config($settings);
    $container             = new Container($config);

    /** @var PermitService $permitService */
    $permitService = $container->get(PermitService::class);

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

            // 1. Ablaufdatum prüfen
            $isExpired = false;
            if (! empty($v['expires_at'])) {
                $expiry = new \DateTimeImmutable($v['expires_at']);
                if ($expiry < new \DateTimeImmutable()) {
                    $isExpired = true;
                }
            }

            if (! $isExpired) {
                // Preis berechnen über die neue Methode im PermitService
                $finalPrice = $permitService->calculateDiscountedPrice($originalPrice, $v);

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
