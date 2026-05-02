<?php

/**
 * Verarbeitet Zahlungs-relevante API-Anfragen v0.12.0
 *
 * @file src/Application/PaymentController.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\PermitService;

/**
 * Verarbeitet Zahlungs-relevante API-Anfragen.
 */
final readonly class PaymentController
{
    public function __construct(
        private PermitService $permitService,
        private ConfigInterface $config,
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

    /**
     * @param array<string, mixed>|null $prefill
     *
     * @return array<string, mixed>
     */
    private function getSettingsArray(?array $prefill = null): array
    {
        $templates = $this->config->get('permit_templates', []);
        $public    = \array_filter((array) $templates, fn (array $template): bool => ($template['public'] ?? false) === true);

        if ($prefill !== null && isset($prefill['template_key'])) {
            $key = (string) $prefill['template_key'];
            if (! isset($public[$key]) && isset($templates[$key])) {
                $public[$key] = $templates[$key];
            }
        }

        return [
            'vereins_name'     => $this->config->get('vereins_name'),
            'vehicle_types'    => $this->config->get('vehicle_types'),
            'purposes'         => $this->config->get('purposes'),
            'public_templates' => $public,
        ];
    }

    public function handleRequest(array $post): void
    {
        $message = '';
        $success = false;

        // Gutschein via URL-Parameter prüfen
        $voucherCode = (string) ($_GET['voucher'] ?? '');
        $prefill     = null;

        if ($voucherCode !== '') {
            $prefill = $this->permitService->getVoucherService()->loadVouchers()[$voucherCode] ?? null;
            if ($prefill && $prefill['used']) {
                $prefill = null;
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $this->permitService->createPendingVerification($post);
                $success = true;
                $message = 'E-Mail wurde versandt. Bitte bestätigen Sie den Link.';
            } catch (\Exception $e) {
                $message = 'Fehler: ' . $e->getMessage();
            }
        }

        $this->render('formular', [
            'message'  => $message,
            'success'  => $success,
            'config'   => $this->config,
            'settings' => $this->getSettingsArray($prefill), // Prefill mitgeben
            'appRoot'  => $this->config->get('root_path'),
            'prefill'  => $prefill,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $templatePath, array $data = []): void
    {
        $appRoot = (string) $this->config->get('root_path');
        \extract($data);
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
