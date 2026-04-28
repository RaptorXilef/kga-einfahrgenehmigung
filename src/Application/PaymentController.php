<?php

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
     * Verarbeitet das Capture von PayPal-Bestellungen.
     */
    public function handleCapture(): void
    {
        \header('Content-Type: application/json');

        try {
            $input = \file_get_contents('php://input');
            $data  = \json_decode((string) $input, true);

            if (! isset($data['orderID'], $data['permitCode'])) {
                throw new \Exception('Fehlende Parameter.');
            }

            $success = $this->permitService->completePayment(
                (string) $data['permitCode'],
                (string) $data['orderID'],
            );

            $this->jsonResponse([
                'success' => $success,
                'message' => $success ? 'Zahlung verarbeitet' : 'Fehler bei Verifizierung',
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
     * Hilfsmethode für saubere JSON-Ausgabe ohne exit.
     *
     * @param array<string, mixed> $data
     */
    private function jsonResponse(array $data): void
    {
        echo \json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_PRETTY_PRINT);
    }
}
