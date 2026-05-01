<?php

/**
 * Verarbeitet Zahlungs-relevante API-Anfragen v0.12.0
 *
 * @file src/Application/PaymentController.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Core\Service\PermitService;

/**
 * Verarbeitet Zahlungs-relevante API-Anfragen.
 */
final readonly class PaymentController
{
    public function __construct(
        private PermitService $permitService,
    ) {
    }

    /**
     * Verarbeitet das Capture (Geldeinzug) von PayPal-Bestellungen.
     * Nutzt das 'token' zur Identifizierung im Warteraum.
     */
    public function handleCapture(): void
    {
        \header('Content-Type: application/json');

        try {
            $input = \file_get_contents('php://input');
            $data  = \json_decode((string) $input, true);

            // Wir erwarten 'token' statt 'permitCode'
            if (! isset($data['orderID'], $data['token'])) {
                throw new \Exception('Fehlende Parameter (orderID oder token).');
            }

            // Der PermitService kümmert sich um die Verifizierung beim Provider
            // und die Finalisierung des Antrags.
            $success = $this->permitService->completePayment(
                (string) $data['token'],
                (string) $data['orderID'],
            );

            echo \json_encode([
                'success' => $success,
                'message' => $success ? 'Zahlung verarbeitet und Antrag finalisiert' : 'Fehler bei Verifizierung',
            ]);
        } catch (\Exception $exception) {
            \http_response_code(400);
            echo \json_encode([
                'success' => false,
                'error'   => $exception->getMessage(),
            ]);
        }
    }

    private function getSettingsArray(): array
    {
        return [
            'vereins_name'  => $this->config->get('vereins_name'),
            'vehicle_types' => $this->config->get('vehicle_types'),
            'purposes'      => $this->config->get('purposes'),
            // NEU: Nur die Vorlagen übergeben, die als 'public' markiert sind
            'public_templates' => \array_filter(
                $this->config->get('permit_templates', []),
                fn ($t) => ($t['public'] ?? false) === true,
            ),
        ];
    }
}
