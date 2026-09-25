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
            ? $this->config->getString('paypal_api_sandbox', 'https://api-m.sandbox.paypal.com')
            : $this->config->getString('paypal_api_live', 'https://api-m.paypal.com');
    }

    #[Override]
    public function createOrder(float $amount): string|false
    {
        $accessToken = $this->getAccessToken();
        $baseUrl = $this->getBaseUrl();

        $curlHandle = \curl_init("$baseUrl/v2/checkout/orders");
        if ($curlHandle === false) {
            return false;
        }

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

        \curl_setopt($curlHandle, \CURLOPT_POSTFIELDS, (string) \json_encode($payload));
        $response = \curl_exec($curlHandle);

        $data = \json_decode((string) $response, true);

        return \is_array($data) && isset($data['id']) && \is_string($data['id']) ? $data['id'] : false;
    }

    #[Override]
    public function captureOrder(string $orderId, float $expectedAmount): bool
    {
        $accessToken = $this->getAccessToken();
        $baseUrl = $this->getBaseUrl();

        $curlHandle = \curl_init("$baseUrl/v2/checkout/orders/$orderId/capture");
        if ($curlHandle === false) {
            return false;
        }

        \curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curlHandle, \CURLOPT_POST, true);
        \curl_setopt($curlHandle, \CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $accessToken",
        ]);

        $response = \curl_exec($curlHandle);
        $httpCode = (int) \curl_getinfo($curlHandle, \CURLINFO_HTTP_CODE);

        if ($httpCode !== 201 && $httpCode !== 200) {
            return false;
        }

        $data = \json_decode((string) $response, true);
        if (!\is_array($data)) {
            return false;
        }

        $status = (string) ($data['status'] ?? '');

        $purchaseUnits = \is_array($data['purchase_units'] ?? null) ? $data['purchase_units'] : [];
        $firstUnit = \is_array($purchaseUnits[0] ?? null) ? $purchaseUnits[0] : [];
        $payments = \is_array($firstUnit['payments'] ?? null) ? $firstUnit['payments'] : [];
        $captures = \is_array($payments['captures'] ?? null) ? $payments['captures'] : [];
        $firstCapture = \is_array($captures[0] ?? null) ? $captures[0] : [];
        $captureData = \is_array($firstCapture['amount'] ?? null) ? $firstCapture['amount'] : [];

        $capturedAmount = (string) ($captureData['value'] ?? '0.00');
        $capturedCurrency = (string) ($captureData['currency_code'] ?? '');

        $formattedExpected = \number_format($expectedAmount, 2, '.', '');

        return $status === 'COMPLETED' && $capturedAmount === $formattedExpected && $capturedCurrency === 'EUR';
    }

    private function getAccessToken(): string
    {
        $baseUrl = $this->getBaseUrl();

        $ppCfg = $this->config->getArray('paypal');
        $mode = $this->config->isTestMode() ? 'sandbox' : 'live';
        $modeCfg = \is_array($ppCfg[$mode] ?? null) ? $ppCfg[$mode] : [];
        $clientId = (string) ($modeCfg['client_id'] ?? '');
        $secret = (string) ($modeCfg['secret'] ?? '');

        $curlHandle = \curl_init("$baseUrl/v1/oauth2/token");
        if ($curlHandle === false) {
            throw new RuntimeException('PayPal cURL Initialisierung fehlgeschlagen.');
        }

        \curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curlHandle, \CURLOPT_USERPWD, "$clientId:$secret");
        \curl_setopt($curlHandle, \CURLOPT_POSTFIELDS, 'grant_type=client_credentials');

        $response = \curl_exec($curlHandle);
        $data = \json_decode((string) $response, true);

        if (!\is_array($data) || !isset($data['access_token'])) {
            throw new RuntimeException('PayPal Authentifizierung fehlgeschlagen. Bitte API-Daten prüfen.');
        }

        return (string) $data['access_token'];
    }
}
