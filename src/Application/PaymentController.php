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
}
