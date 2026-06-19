<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Infrastructure\Config\Config;

/**
 * PayPal-Implementierung des PaymentProviders.
 *
 * Kommuniziert mit der PayPal REST API v2 zur sicheren Verifizierung von Zahlungen.
 * Gleicht den tatsächlich gezahlten Betrag mit dem erwarteten Betrag ab.
 *
 * Infrastruktur-Treiber für die PayPal REST-API (v2 Checkout Orders).
 * Wickelt die OAuth2-Bearer-Token-Generierung ab, erstellt Zahlungsaufträge (Orders) via cURL
 * und validiert Transaktionen beim Capture-Prozess durch harten Abgleich mit dem System-Soll-Betrag.
 * Kontext: Schnittstelle für bargeldlose Online-Zahlungsabwicklungen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PayPalService implements PaymentProviderInterface
{
    private const string API_BASE_SANDBOX = 'https://api-m.sandbox.paypal.com';
    private const string API_BASE_LIVE    = 'https://api-m.paypal.com';

    public function __construct(
        private Config $config,
    ) {
    }

    // --- Public API ---

    /**
     * Erstellt eine transaktionsbereite Order in der PayPal-Cloud für den Checkout.
     *
     * @param float $amount Der einzuziehende Bruttobetrag (wird auf 2 Dezimalstellen formatiert).
     *
     * @return string|false Die von PayPal vergebene Order-ID (z.B. 'EC-xxxx') oder False bei API-Fehlern.
     */
    public function createOrder(float $amount): string|false
    {
        $accessToken = $this->getAccessToken();
        $baseUrl     = $this->config->isTestMode() ? self::API_BASE_SANDBOX : self::API_BASE_LIVE;

        $curlHandle = \curl_init("$baseUrl/v2/checkout/orders");
        \curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curlHandle, \CURLOPT_POST, true);
        \curl_setopt($curlHandle, \CURLOPT_HTTPHEADER, [
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

        \curl_setopt($curlHandle, \CURLOPT_POSTFIELDS, \json_encode($payload));
        $response = \curl_exec($curlHandle);
        $data     = \json_decode((string) $response, true);
        // curl_close entfernt (deprecated in IDE)

        return $data['id'] ?? false;
    }

    /**
     * Erfasst (captured) und verifiziert eine vom Kunden freigegebene PayPal-Zahlung.
     * Prüft den Status auf 'COMPLETED' und vergleicht den real eingezogenen Betrag
     * mit dem erwarteten Preis der Genehmigung, um Betrug/Manipulationen auszuschließen.
     *
     * Verifiziert eine Zahlung und prüft, ob der gezahlte Betrag korrekt ist.
     *
     * @param string $orderId        Die zu buchende PayPal-Order-ID.
     * @param float  $expectedAmount Der im System hinterlegte Soll-Betrag der Genehmigung (z.B. 3.00 oder 10.00).
     *
     * @return bool True, wenn das Geld erfolgreich eingezogen wurde und der Betrag exakt stimmt.
     */
    public function captureOrder(string $orderId, float $expectedAmount): bool
    {
        $accessToken = $this->getAccessToken();
        $baseUrl     = $this->config->isTestMode() ? self::API_BASE_SANDBOX : self::API_BASE_LIVE;

        // 1. PayPal API aufrufen, um das Geld endgültig einzuziehen ("Capture")
        $curlHandle = \curl_init("$baseUrl/v2/checkout/orders/$orderId/capture");
        \curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curlHandle, \CURLOPT_POST, true);
        \curl_setopt($curlHandle, \CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $accessToken",
        ]);

        $response = \curl_exec($curlHandle);
        $httpCode = \curl_getinfo($curlHandle, \CURLINFO_HTTP_CODE);
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
        $captureData      = $data['purchase_units'][0]['payments']['captures'][0]['amount'] ?? [];
        $capturedAmount   = $captureData['value'] ?? '0.00';
        $capturedCurrency = $captureData['currency_code'] ?? ''; // Währung auslesen

        // Wir formatieren deinen erwarteten Preis auf das PayPal-Format (String mit 2 Nachkommastellen)
        $formattedExpected = \number_format($expectedAmount, 2, '.', '');

        // Nur wenn Status, Betrag UND Währung (EUR) exakt stimmen!
        return $status === 'COMPLETED' && $capturedAmount === $formattedExpected && $capturedCurrency === 'EUR';
    }

    // --- Private Auth ---

    /**
     * Holt den temporären OAuth2 Access Token von PayPal.
     *
     * Fordert ein zeitlich begrenztes OAuth2-Bearer-Access-Token via Client-Credentials an.
     * Unterscheidet anhand des Testmodus automatisch zwischen Sandbox- und Live-API-Schlüsseln.
     *
     * @return string Der Autorisierungs-Token für nachfolgende API-Header.
     */
    private function getAccessToken(): string
    {
        $baseUrl = $this->config->isTestMode() ? self::API_BASE_SANDBOX : self::API_BASE_LIVE;

        // Dynamische Auswahl der Credentials basierend auf dem Modus
        $ppCfg    = $this->config->get('paypal');
        $mode     = $this->config->isTestMode() ? 'sandbox' : 'live';
        $clientId = $ppCfg[$mode]['client_id'];
        $secret   = $ppCfg[$mode]['secret'];

        $curlHandle = \curl_init("$baseUrl/v1/oauth2/token");
        \curl_setopt($curlHandle, \CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($curlHandle, \CURLOPT_USERPWD, "$clientId:$secret");
        \curl_setopt($curlHandle, \CURLOPT_POSTFIELDS, 'grant_type=client_credentials');

        $response = \curl_exec($curlHandle);
        $data     = \json_decode((string) $response, true);
        // curl_close entfernt (deprecated in IDE)

        if (! isset($data['access_token'])) {
            throw new \RuntimeException('PayPal Authentifizierung fehlgeschlagen. Bitte API-Daten prüfen.');
        }

        return (string) $data['access_token'];
    }
}
