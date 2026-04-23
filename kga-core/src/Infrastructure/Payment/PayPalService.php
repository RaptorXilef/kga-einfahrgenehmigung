<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * PayPal-Implementierung des PaymentProviders.
 *
 * Kommuniziert mit der PayPal REST API v2 zur sicheren Verifizierung von Zahlungen.
 *
 * @file      src/Infrastructure/Payment/PayPalService.php
 *
 * @copyright (c) 2026 Felix Maywald. All rights reserved.
 * @license   https://github.com/RaptorXilef/kga-einfahrgenehmigung/blob/main/LICENSE
 *
 * @link      https://github.com/RaptorXilef/kga-einfahrgenehmigung/
 *
 * @author    Felix Maywald (@RaptorXilef)
 *
 * @since     0.1.0
 * - feat(payment): Implementierung der sicheren Server-Side Capture Logik.
 */

declare(strict_types=1);

namespace App\Infrastructure\Payment;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Infrastructure\Config\Config;
use RuntimeException;

final class PayPalService implements PaymentProviderInterface
{
    private const API_BASE_SANDBOX = 'https://api-m.sandbox.paypal.com';
    private const API_BASE_LIVE = 'https://api-m.paypal.com';

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function captureOrder(string $orderId): bool
    {
        $accessToken = $this->getAccessToken();
        $baseUrl = $this->config->isTestMode() ? self::API_BASE_SANDBOX : self::API_BASE_LIVE;

        $ch = curl_init("$baseUrl/v2/checkout/orders/$orderId/capture");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer $accessToken",
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 201 && $httpCode !== 200) {
            return false;
        }

        $data = json_decode((string)$response, true);

        // WICHTIG: Wir prüfen den Status UND den Betrag (Server-Side SSOT)
        $status = $data['status'] ?? '';
        $amount = $data['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? '0';
        $targetPrice = number_format((float)$this->config->get('preis', 3.00), 2, '.', '');

        return $status === 'COMPLETED' && $amount === $targetPrice;
    }

    private function getAccessToken(): string
    {
        $baseUrl = $this->config->isTestMode() ? self::API_BASE_SANDBOX : self::API_BASE_LIVE;
        $clientId = $this->config->get('paypal_client_id');
        $secret = $this->config->get('paypal_secret');

        $ch = curl_init("$baseUrl/v1/oauth2/token");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "$clientId:$secret");
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

        $response = curl_exec($ch);
        $data = json_decode((string)$response, true);
        curl_close($ch);

        if (!isset($data['access_token'])) {
            throw new RuntimeException("PayPal Authentifizierung fehlgeschlagen.");
        }

        return (string)$data['access_token'];
    }
}
