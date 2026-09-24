<?php

declare(strict_types=1);

namespace App\Modules\Finance\Infrastructure\Payment;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use Override;
use RuntimeException;

/**
 * PayPal-Implementierung des PaymentProviders.
 *
 * Kommuniziert mit der PayPal REST API v2 zur sicheren Verifizierung von Zahlungen.
 * Gleicht den tatsächlich gezahlten Betrag mit dem erwarteten Betrag ab.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PayPalService implements PaymentProviderInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    private function getBaseUrl(): string
    {
        return $this->config->isTestMode()
            ? $this->config->get('paypal_api_sandbox', 'https://api-m.sandbox.paypal.com')
            : $this->config->get('paypal_api_live', 'https://api-m.paypal.com');
    }

    #[Override]
    public function createOrder(float $amount): string|false
    {
        $accessToken = $this->getAccessToken();
        $baseUrl = $this->getBaseUrl();

        $curlHandle = \curl_init("$baseUrl/v2/checkout/orders");
        \curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curlHandle, \CURLOPT_POST, true);
        \curl_setopt($curlHandle, \CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $accessToken",
        ]);

        $payload = [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => \number_format($amount, 2, '.', ''),
                ],
            ]],
        ];

        \curl_setopt($curlHandle, \CURLOPT_POSTFIELDS, \json_encode($payload));
        $response = \curl_exec($curlHandle);

        $data = \json_decode((string) $response, true);

        return $data['id'] ?? false;
    }

    #[Override]
    public function captureOrder(string $orderId, float $expectedAmount): bool
    {
        $accessToken = $this->getAccessToken();
        $baseUrl = $this->getBaseUrl();

        $curlHandle = \curl_init("$baseUrl/v2/checkout/orders/$orderId/capture");
        \curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curlHandle, \CURLOPT_POST, true);
        \curl_setopt($curlHandle, \CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $accessToken",
        ]);

        $response = \curl_exec($curlHandle);
        $httpCode = \curl_getinfo($curlHandle, \CURLINFO_HTTP_CODE);

        if ($httpCode !== 201 && $httpCode !== 200) {
            return false;
        }

        $data = \json_decode((string) $response, true);
        $status = $data['status'] ?? '';

        $captureData = $data['purchase_units'][0]['payments']['captures'][0]['amount'] ?? [];
        $capturedAmount = $captureData['value'] ?? '0.00';
        $capturedCurrency = $captureData['currency_code'] ?? '';

        $formattedExpected = \number_format($expectedAmount, 2, '.', '');

        return $status === 'COMPLETED' && $capturedAmount === $formattedExpected && $capturedCurrency === 'EUR';
    }

    private function getAccessToken(): string
    {
        $baseUrl = $this->getBaseUrl();

        $ppCfg = $this->config->get('paypal');
        $mode = $this->config->isTestMode() ? 'sandbox' : 'live';
        $clientId = $ppCfg[$mode]['client_id'];
        $secret = $ppCfg[$mode]['secret'];

        $curlHandle = \curl_init("$baseUrl/v1/oauth2/token");
        \curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curlHandle, \CURLOPT_USERPWD, "$clientId:$secret");
        \curl_setopt($curlHandle, \CURLOPT_POSTFIELDS, 'grant_type=client_credentials');

        $response = \curl_exec($curlHandle);
        $data = \json_decode((string) $response, true);

        if (!isset($data['access_token'])) {
            throw new RuntimeException('PayPal Authentifizierung fehlgeschlagen. Bitte API-Daten prüfen.');
        }

        return (string) $data['access_token'];
    }
}
