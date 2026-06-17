<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\View\TemplateRenderer;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Service\BankQrGenerator;

/**
 * Action für die Erfolgs- und Bestätigungsseite nach Abschluss eines Antrags.
 * Generiert bei Bedarf Bank-QR-Codes (EPC) für offene Überweisungen und zeigt
 * dem Benutzer die finalen Zahlungsanweisungen an.
 *
 * Path: src/Application/Actions/SuccessAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class SuccessAction implements ViewActionInterface
{
    public function __construct(
        private BankQrGenerator $bankQrGenerator,
        private ConfigInterface $config,
        private StorageInterface $storage,
        private TemplateRenderer $renderer,
    ) {
    }

    /**
     * TODO DOCBLOCK
     * Haupt-Request-Handler für die Success-Seite.
     * Validiert das Ticket und bereitet die Bezahlinformationen auf.
     */
    public function execute(array $requestData): void
    {
        $code   = (string) ($requestData['code'] ?? '');
        $method = (string) ($requestData['method'] ?? 'wire');

        $permit = $this->storage->findByHash($code);
        if (! $permit) {
            \header('Location: index.php');
            exit;
        }

        $epcData = '';
        $usage   = '';
        if ($method === 'wire' && $permit->getStatus() !== 'bezahlt') {
            // Verwendungszweck aus dem Code generieren (letzte 6 Zeichen)
            $shortCode = \substr($permit->code, -6);
            $nameParts = \explode(' ', $permit->getOwnerName());
            $vorname   = $nameParts[0] ?? 'Unbekannt';
            $nachname  = $nameParts[\count($nameParts) - 1] ?? 'Unbekannt';
            $usage     = "EFG-{$nachname}-{$vorname}-{$shortCode}";

            $epcData = $this->bankQrGenerator->generate($permit->getPrice(), $usage);
        }

        // Dynamische Zahlungslogik
        $requirePayment = (bool) $this->config->get('require_payment_for_validity', false);
        $dueDays        = (int) $this->config->get('payment_due_days', 14);

        // Frist ab Erstellungsdatum berechnen (wie in der Mail)
        $dueDate = $permit->getCreatedAt()->modify("+$dueDays days")->format('d.m.Y');

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
