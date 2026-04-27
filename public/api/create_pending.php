<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0
declare(strict_types=1);

\header('Content-Type: application/json');

$appRoot = (function (): string {
    $dir = __DIR__;
    while ($dir !== \dirname($dir)) {
        if (\file_exists($dir . '/vendor/autoload.php')) {
            return $dir;
        }
        $dir = \dirname($dir);
    }

    return \dirname(__DIR__, 2); // Falls wir tief in /public/api/ liegen
})();

require_once $appRoot . '/vendor/autoload.php';

use App\Bootstrap\Container;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Core\Service\PermitService;
use App\Infrastructure\Config\Config;

try {
    $settings              = require_once $appRoot . '/config/config.php';
    $settings['root_path'] = $appRoot;
    $container             = new Container(new Config($settings));

    /** @var PermitService $permitService */
    $permitService = $container->get(PermitService::class);
    /** @var PaymentProviderInterface $paypal */
    $paypal = $container->get(PaymentProviderInterface::class);

    // 1. Genehmigung erstellen, aber E-Mails NOCH NICHT senden (Parameter false) (Status: wartend)
    $permit = $permitService->createPermit($_POST, false);

    // 2. PayPal Order reservieren
    $paypalOrderId = $paypal->createOrder($permit->preisSnapshot);

    if (! $paypalOrderId) {
        throw new \Exception('PayPal-Schnittstelle antwortet nicht.');
    }

    echo \json_encode([
        'success'       => true,
        'code'          => $permit->code,
        'paypalOrderId' => $paypalOrderId,
    ]);

} catch (\Exception $e) {
    \http_response_code(400);
    echo \json_encode(['success' => false, 'error' => $e->getMessage()]);
}
