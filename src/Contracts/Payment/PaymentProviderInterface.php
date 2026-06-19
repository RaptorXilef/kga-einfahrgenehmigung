<?php

declare(strict_types=1);

namespace App\Contracts\Payment;

/**
 * Interface für externe Zahlungsabwickler-Schnittstellen.
 *
 * Regelt die Initiierung von Bezahlvorgängen (Order-Erstellung) sowie die
 * finale Verifizierung und Erfassung (Capture) von Transaktionen.
 * Kontext: Abstraktion der Zahlungs-Gateway-API (z.B. für PayPal-Integrationen).
 *
 * Definiert die notwendigen Methoden zur Verifizierung und Abwicklung von Zahlungen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface PaymentProviderInterface
{
    /**
     * Erstellt eine Zahlungs-Order Transaktion/Bestellung beim Zahlungsdienstleister.
     *
     * @param float $amount Der zu zahlende Bruttobetrag.
     *
     * @return string|false Die vom Provider generierte Order-ID bei Erfolg, andernfalls false.
     */
    public function createOrder(float $amount): string|false;

    /**
     * Verifiziert und finalisiert eine vom Kunden autorisierte Zahlung.
     * Schützt das System vor Manipulationen durch Abgleich des realen Betrags mit der Erwartung.
     *
     * @param string $orderId        Die zu erfassende Order-ID des Zahlungsanbieters.
     * @param float  $expectedAmount Der im System hinterlegte Soll-Betrag der Genehmigung.
     *
     * @return bool True, wenn die Zahlung erfolgreich eingezogen und stimmig ist.
     */
    public function captureOrder(string $orderId, float $expectedAmount): bool;
}
