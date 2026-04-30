<?php

/**
 * Verarbeitet Zahlungs-relevante API-Anfragen v0.12.1
 *
 * @file src/Application/PaymentController.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Payment\PaymentProviderInterface;
use App\Core\Service\PermitService;

/**
 * Verarbeitet Zahlungs-relevante API-Anfragen.
 */
final readonly class PaymentController
{
    public function __construct(
        private PermitService $permitService,
        private PaymentProviderInterface $paymentProvider,
    ) {
    }

    /**
     * Veraltet: handleCreatePending wird im neuen Email-First Workflow
     * durch PermitService::createPendingVerification ersetzt.
     */
    public function handleCreatePending(array $post): void // TODO REMOVE
    {
        // ... (kann theoretisch entfernt werden, wenn v0.12.1 stabil läuft)

        \header('Content-Type: application/json');

        try {
            // 1. Genehmigung erstellen (Status: wartend, noch keine Mails)
            $permit = $this->permitService->createPermit($post, false);

            // 2. PayPal Order reservieren
            $paypalOrderId = $this->paymentProvider->createOrder($permit->preisSnapshot);

            if (! $paypalOrderId) {
                throw new \Exception('PayPal-Schnittstelle antwortet nicht.');
            }

            $this->jsonResponse([
                'success'       => true,
                'code'          => $permit->code,
                'paypalOrderId' => $paypalOrderId,
            ]);
        } catch (\Exception $exception) {
            \http_response_code(400);
            $this->jsonResponse([
                'success' => false,
                'error'   => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Verarbeitet das Capture (Geldeinzug) von PayPal-Bestellungen.
     * Nutzt jetzt das 'token' zur Identifizierung im Warteraum.
     */
    public function handleCapture(): void
    {
        \header('Content-Type: application/json');

        try {
            $input = \file_get_contents('php://input');
            $data  = \json_decode((string) $input, true);

            // NEU: Wir erwarten 'token' statt 'permitCode'
            if (! isset($data['orderID'], $data['token'])) {
                throw new \Exception('Fehlende Parameter (orderID oder token).');
            }

            // PermitService::completePayment verschiebt den Antrag bei Erfolg
            // von verified_pending.json in die finale daten.json
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

    /**
     * Hilfsmethode für saubere JSON-Ausgabe.
     *
     * @param array<string, mixed> $data
     */
    private function jsonResponse(array $data): void
    {
        echo \json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
    }
}
