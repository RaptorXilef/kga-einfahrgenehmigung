<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Response\JsonResponse;
use App\Contracts\Config\ConfigInterface;
use App\Core\Service\PermitService;

/**
 * TODO Prüfen, ob die Klasse weg kann, da PermitController eigentlich alles abdeckt?!?
 *
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
            // Wirft eine \JsonException, die direkt im catch(\Exception) unten gefangen wird!
            $data = \json_decode((string) $input, true, 512, \JSON_THROW_ON_ERROR);

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
     * Rendert die Bezahl- und Antragsformulare.
     *
     * @param string               $templatePath Dateiname des Page-Templates.
     * @param array<string, mixed> $data         Injektionsvariablen.
     */
    private function render(string $templatePath, array $data = []): void
    {
        $appRoot = (string) $this->config->get('root_path');
        // Zwingender Sicherheits-Fix gegen Variable Overwrite / LFI
        \extract($data, \EXTR_SKIP);
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
