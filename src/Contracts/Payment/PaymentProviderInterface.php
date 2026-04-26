<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Interface für Zahlungsanbieter.
 *
 * Definiert die notwendigen Methoden zur Verifizierung und Abwicklung von Zahlungen.
 *
 * @file      src/Contracts/Payment/PaymentProviderInterface.php
 *
 * @copyright (c) 2026 Felix Maywald. All rights reserved.
 * @license   https://github.com/RaptorXilef/kga-einfahrgenehmigung/blob/main/LICENSE
 *
 * @link      https://github.com/RaptorXilef/kga-einfahrgenehmigung/
 *
 * @author    Felix Maywald (@RaptorXilef)
 *
 * @since     0.1.0
 * - feat(payment): Definition der Schnittstelle für die Zahlungsverifizierung.
 */

declare(strict_types=1);

namespace App\Contracts\Payment;

interface PaymentProviderInterface
{
    /**
     * Verifiziert eine Zahlung beim Anbieter und schließt diese ab.
     *
     * @param string $orderId Die vom Client übermittelte Order-ID.
     * @param float $expectedAmount Der Betrag, der laut deiner Config gezahlt werden muss.
     *
     * @return bool True, wenn die Zahlung erfolgreich verifiziert und abgeschlossen wurde.
     */
    public function captureOrder(string $orderId, float $expectedAmount): bool;
}
