<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Service zur Generierung von EPC-GiroCodes für SEPA-Überweisungen.
 *
 * Path: src/Core/Service/BankQrGenerator.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class BankQrGenerator
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    /**
     * Erzeugt einen rohen EPC-QR-Code Payload (GiroCode) nach der SEPA-Dokumentation für Banking-Apps.
     *
     * @param float  $amount    Der Überweisungsbetrag.
     * @param string $reference Der strukturierte Verwendungszweck.
     *
     * @return string Zeilenumbruch-getrennter EPC-Payload.
     */
    public function generate(float $amount, string $reference): string
    {
        return "BCD\n001\n1\nSCT\n" .
            $this->config->get('bic') . "\n" .
            $this->config->get('kontoinhaber') . "\n" .
            $this->config->get('iban') . "\n" .
            'EUR' . \number_format($amount, 2, '.', '') . "\n" .
            "\n" . // Purpose Code leer
            "\n" . // Structured Reference leer
            $reference;
    }
}
