<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Orchestriert den Antragsprozess für Pächter (Hauptformular).
 *
 * @file      public/index.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Core\Service\PermitService;

/**
 * Orchestriert den Antragsprozess für Pächter (Hauptformular).
 */
final readonly class PermitController
{
    public function __construct(
        private ConfigInterface $config,
        private PermitService $permitService,
    ) {
    }

    /**
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleRequest(array $post): void
    {
        $message = '';
        $success = false;

        // Formular-Verarbeitung (nur bei POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $voucherCode = \trim((string) ($post['voucher'] ?? ''));

                // FALL A: Gutschein wurde eingegeben
                if ($voucherCode !== '') {
                    $voucher = $this->permitService->getVoucherService()->useVoucher($voucherCode);

                    if (! $voucher) {
                        throw new \Exception('Dieser Gutscheincode ist ungültig oder wurde bereits verwendet.');
                    }

                    // Gutschein gültig! Wir erstellen die Genehmigung direkt als 'bezahlt'
                    $post['status']            = 'bezahlt';
                    $post['internerKommentar'] = 'Eingelöster Gutschein: ' . $voucherCode . ' (Grund: ' . $voucher['reason'] . ')';

                    $permit = $this->permitService->createPermit($post, true);

                    $success = true;
                    $message = "Gutschein erfolgreich eingelöst! Ihre Genehmigung {$permit->code} wurde aktiviert und versandt.";
                }
                // FALL B: Normaler Ablauf (Überweisung/E-Mail-Verifikation)
                else {
                    $this->permitService->createPendingVerification($post);
                    $success = true;
                    $message = 'Antrag fast fertig! Bitte prüfen Sie Ihr E-Mail-Postfach und bestätigen Sie Ihre E-Mail-Adresse.';
                }
            } catch (\Exception $exception) {
                $message = 'Fehler: ' . $exception->getMessage();
            }
        }

        // View rendern
        $this->render('formular', [
            'message'  => $message,
            'success'  => $success,
            'config'   => $this->config,
            'settings' => $this->getSettingsArray(),
            'appRoot'  => $this->config->get('root_path'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name'  => $this->config->get('vereins_name'),
            'vehicle_types' => $this->config->get('vehicle_types'),
            'purposes'      => $this->config->get('purposes'),
            'jahresFarbe'   => $this->config->get('jahresFarbe'),
        ];
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
