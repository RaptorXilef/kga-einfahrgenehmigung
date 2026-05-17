<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

// Path: src/Contracts/Payment/PaymentProviderInterface.php

declare(strict_types=1);

namespace App\Contracts\Payment;

/**
 * Interface für Zahlungsanbieter.
 *
 * Definiert die notwendigen Methoden zur Verifizierung und Abwicklung von Zahlungen.
 */
interface PaymentProviderInterface
{
    /**
     * Erstellt eine Transaktion/Order/Bestellung beim Anbieter.
     *
     * @param  float       $amount Der zu zahlende Betrag
     * @return string|null Die Order-ID des Anbieters oder null bei Fehler
     */
    public function createOrder(float $amount): string|false;

    /**
     * Verifiziert eine Zahlung beim Anbieter und schließt diese ab.
     *
     * @param string $orderId        Die vom Client übermittelte Order-ID.
     * @param float  $expectedAmount Der Betrag, der laut deiner Config gezahlt werden muss.
     *
     * @return bool True, wenn die Zahlung erfolgreich verifiziert und abgeschlossen wurde.
     */
    public function captureOrder(string $orderId, float $expectedAmount): bool;
}
