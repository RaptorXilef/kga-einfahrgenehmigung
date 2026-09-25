<?php

declare(strict_types=1);

namespace App\Contracts\System;

/**
 * Port zur Generierung von QR-Codes (als Binär-PNG oder Base64-Data-URI).
 * Entkoppelt die Application-Schicht vollständig von Drittanbieter-Bibliotheken (Endroid\QrCode).
 */
interface QrCodeGeneratorInterface
{
    /**
     * Erzeugt einen PNG-QR-Code und gibt den Inhalt inklusive MIME-Type zurück.
     *
     * @return array{content: string, mimeType: string}
     */
    public function generatePng(string $data, int $size = 200, int $margin = 10): array;

    /**
     * Erzeugt einen PNG-QR-Code direkt als Base64-codierte Data-URI (z.B. für PDF-Dokumente).
     */
    public function generateDataUri(string $data, int $size = 160, int $margin = 0): string;
}
