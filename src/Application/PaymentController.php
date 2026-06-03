<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Core\Service\PermitService;

/**
 * Controller zur Abwicklung und Erfassung externer Zahlungen (z.B. PayPal-Webhook/Capture).
 *
 * Verwaltet zusätzlich die Erstellung von Erstanträgen sowie Vorbefüllungen durch Gutscheine.
 *
 * Path: src/Application/PaymentController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PaymentController
{
    public function __construct(
        private ConfigInterface $config,
        private PermitService $permitService,
    ) {
    }

    /**
     * Verarbeitet asynchrone REST-Zahlungsbestätigungen (JSON-Input stream).
     * Finalisiert die Genehmigung bei erfolgreicher Transaktions-Verifizierung.
     *
     * Verarbeitet das Capture (Geldeinzug) von PayPal-Bestellungen.
     * Nutzt das 'token' zur Identifizierung im Warteraum.
     *
     * @return void Schreibt JSON direkt in den Output-Stream.
     */
    public function handleCapture(): void
    {
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

            if ($success) {
                JsonResponse::success(['message' => 'Zahlung verarbeitet und Antrag finalisiert']);
            } else {
                JsonResponse::error('Fehler bei Verifizierung');
            }

        } catch (\Exception $exception) {
            JsonResponse::error($exception->getMessage(), 400);
        }
    }

    /**
     * Generiert Template-Einstellungen und filtert öffentliche Antragsformulare.
     * Berücksichtigt pre-filled Datensätze bei aktiven Gutscheincodes.
     *
     * @param array<string, mixed>|null $prefill Optionale Gutschein-Stammdaten.
     *
     * @return array<string, mixed> Template-Konfigurationsarray.
     */
    private function getSettingsArray(?array $prefill = null): array
    {
        $templates = $this->config->get('permit_templates', []);
        $public    = \array_filter(
            (array) $templates,
            fn (array $template): bool => ($template['public'] ?? false) === true,
        );

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

    /**
     * Verarbeitet das Absenden des öffentlichen Antragsformulars per POST.
     * Initiiert die Verifikationskette oder wertet vorausgefüllte Gutschein-Parameter aus $_GET aus.
     *
     * @param array<string, mixed> $post Entspricht $_POST
     */
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
            if (($post['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
                $message = 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
            } else {
                try {
                    $this->permitService->createPendingVerification($post);
                    $success = true;
                    $message = 'E-Mail wurde versandt. Bitte bestätigen Sie den Link.';
                } catch (\Exception $e) {
                    $message = 'Fehler: ' . $e->getMessage();
                }
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
     * Rendert die Bezahl- und Antragsformulare.
     *
     * @param string               $templatePath Dateiname des Page-Templates.
     * @param array<string, mixed> $data         Injektionsvariablen.
     */
    private function render(string $templatePath, array $data = []): void
    {
        $appRoot = (string) $this->config->get('root_path');
        \extract($data);
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
