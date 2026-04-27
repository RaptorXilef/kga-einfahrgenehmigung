<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * PayPal-Implementierung des PaymentProviders.
 *
 * Kommuniziert mit der PayPal REST API v2 zur sicheren Verifizierung von Zahlungen.
 * Gleicht den tatsächlich gezahlten Betrag mit dem erwarteten Betrag ab.
 *
 * @file      src/Infrastructure/Payment/PayPalService.php
 *
 * @copyright (c) 2026 Felix Maywald. All rights reserved.
 * @license   https://github.com/RaptorXilef/kga-einfahrgenehmigung/blob/main/LICENSE
 *
 * @link      https://github.com/RaptorXilef/kga-einfahrgenehmigung/
 *
 * @author    Felix Maywald (@RaptorXilef)
 */

declare(strict_types=1);

namespace App\Infrastructure\Payment;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Infrastructure\Config\Config;

final readonly class PayPalService implements PaymentProviderInterface
{
    private const string API_BASE_SANDBOX = 'https://api-m.sandbox.paypal.com';
    private const string API_BASE_LIVE    = 'https://api-m.paypal.com';

    public function __construct(
        private Config $config,
    ) {
    }

    public function createOrder(float $amount): string|false
    {
        $accessToken = $this->getAccessToken();
        $baseUrl     = $this->config->isTestMode() ? self::API_BASE_SANDBOX : self::API_BASE_LIVE;

        $ch = \curl_init("$baseUrl/v2/checkout/orders");
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_POST, true);
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $accessToken",
        ]);

        $payload = [
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => 'EUR',
                    'value'         => \number_format($amount, 2, '.', ''),
                ],
            ]],
        ];

        \curl_setopt($ch, \CURLOPT_POSTFIELDS, \json_encode($payload));
        $response = \curl_exec($ch);
        $data     = \json_decode((string) $response, true);
        // curl_close entfernt (deprecated in IDE)

        return $data['id'] ?? false;
    }

    /**
     * Verifiziert eine Zahlung und prüft, ob der gezahlte Betrag korrekt ist.
     *
     * @param float $expectedAmount Der serverseitig erwartete Betrag (z.B. 3.00 oder 10.00).
     */
    public function captureOrder(string $orderId, float $expectedAmount): bool
    {
        $accessToken = $this->getAccessToken();
        $baseUrl     = $this->config->isTestMode() ? self::API_BASE_SANDBOX : self::API_BASE_LIVE;

        // 1. PayPal API aufrufen, um das Geld endgültig einzuziehen ("Capture")
        $ch = \curl_init("$baseUrl/v2/checkout/orders/$orderId/capture");
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_POST, true);
        \curl_setopt($ch, \CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $accessToken",
        ]);

        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        // curl_close entfernt (deprecated in IDE)

        // Prüfen, ob die API-Anfrage technisch erfolgreich war (200 OK oder 201 Created)
        if ($httpCode !== 201 && $httpCode !== 200) {
            return false;
        }

        $data = \json_decode((string) $response, true);

        // 2. STATUS-PRÜFUNG
        $status = $data['status'] ?? '';

        // 3. BETRAGS-PRÜFUNG (WICHTIG für Sicherheit!)
        // PayPal liefert den Betrag als String im Deep-Array: purchase_units -> payments -> captures -> amount -> value
        $capturedAmount = $data['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? '0.00';

        // Wir formatieren deinen erwarteten Preis auf das PayPal-Format (String mit 2 Nachkommastellen)
        $formattedExpected = \number_format($expectedAmount, 2, '.', '');

        // Nur wenn Status 'COMPLETED' UND der Preis exakt mit unserem System übereinstimmt:
        return $status === 'COMPLETED' && $capturedAmount === $formattedExpected;
    }

    /**
     * Holt den temporären OAuth2 Access Token von PayPal.
     */
    private function getAccessToken(): string
    {
        $baseUrl = $this->config->isTestMode() ? self::API_BASE_SANDBOX : self::API_BASE_LIVE;

        // Dynamische Auswahl der Credentials basierend auf dem Modus
        $ppCfg    = $this->config->get('paypal');
        $mode     = $this->config->isTestMode() ? 'sandbox' : 'live';
        $clientId = $ppCfg[$mode]['client_id'];
        $secret   = $ppCfg[$mode]['secret'];

        $ch = \curl_init("$baseUrl/v1/oauth2/token");
        \curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, \CURLOPT_USERPWD, "$clientId:$secret");
        \curl_setopt($ch, \CURLOPT_POSTFIELDS, 'grant_type=client_credentials');

        $response = \curl_exec($ch);
        $data     = \json_decode((string) $response, true);
        // curl_close entfernt (deprecated in IDE)

        if (! isset($data['access_token'])) {
            throw new \RuntimeException('PayPal Authentifizierung fehlgeschlagen. Bitte API-Daten prüfen.');
        }

        return (string) $data['access_token'];
    }
}
