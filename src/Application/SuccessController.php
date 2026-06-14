<?php

// Path: src\Application\SuccessController.php
declare(strict_types=1);

namespace App\Application;

use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\BankQrGenerator;

/**
 * Controller für die Erfolgs- und Bestätigungsseite nach Abschluss eines Antrags.
 *
 * Generiert bei Bedarf Bank-QR-Codes (EPC) für offene Überweisungen und zeigt
 * dem Benutzer die finalen Zahlungsanweisungen an.
 *
 * Path: src/Application/SuccessController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SuccessController
{
    public function __construct(
        private BankQrGenerator $bankQrGenerator,
        private ConfigInterface $config,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
    ) {
    }

    /**
     * Haupt-Request-Handler für die Success-Seite.
     * Validiert das Ticket und bereitet die Bezahlinformationen auf.
     *
     * @param array<string, mixed> $get Entspricht $_GET.
     */
    public function handleRequest(array $get): void
    {
        $code   = (string) ($get['code'] ?? '');
        $method = (string) ($get['method'] ?? 'wire');

        $permit = $this->storage->findByHash($code);
        if (! $permit) {
            \header('Location: index.php');
            exit;
        }

        $epcData = '';
        $usage   = '';
        if ($method === 'wire' && $permit->status->current !== 'bezahlt') {
            // Verwendungszweck aus dem Code generieren (letzte 6 Zeichen)
            $shortCode = \substr($permit->code, -6);
            $nameParts = \explode(' ', $permit->owner->name);
            $vorname   = $nameParts[0] ?? 'Unbekannt';
            $nachname  = $nameParts[\count($nameParts) - 1] ?? 'Unbekannt';
            $usage     = "EFG-{$nachname}-{$vorname}-{$shortCode}";

            $epcData = $this->bankQrGenerator->generate($permit->validity->preis, $usage);
        }

        // Dynamische Zahlungslogik
        $requirePayment = (bool) $this->config->get('require_payment_for_validity', false);
        $dueDays        = (int) $this->config->get('payment_due_days', 14);
        // Frist ab Erstellungsdatum berechnen (wie in der Mail)
        $dueDate = $permit->erstellt->modify("+$dueDays days")->format('d.m.Y');

        // [x] sortiert
        $this->renderer->render('checkout/success', [
            'dueDate'        => $dueDate,
            'epcData'        => \urlencode($epcData),
            'method'         => $method,
            'permit'         => $permit,
            'requirePayment' => $requirePayment,
            'usage'          => $usage,
        ]);
    }
}
