<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

use InvalidArgumentException;
use Stringable;

/**
 * TODO Auf deutsch schreiben
 * Value Object representing a vehicle license plate (Kennzeichen).
 *
 * Enforces standardized formatting and strict structural validation.
 */
final readonly class LicensePlate implements Stringable
{
    /**
     * @var string The formatted license plate string.
     */
    public string $value;

    /**
     * @param string $plate The raw license plate input.
     *
     * @throws InvalidArgumentException If the plate is empty or invalid.
     */
    public function __construct(string $plate)
    {
        $plate = \trim(\strtoupper($plate));

        if ($plate === '') {
            throw new InvalidArgumentException('Das Kennzeichen darf nicht leer sein.');
        }

        // Erlaube nur Buchstaben, Zahlen, Leerzeichen und Bindestriche (International)
        if (!\preg_match('/^[A-ZÄÖÜ0-9\-\s]+$/u', $plate)) {
            throw new InvalidArgumentException('Bitte geben Sie nur ein gültiges Kennzeichen ein (Buchstaben, Zahlen, Leerzeichen, Bindestrich). Sonderzeichen wie / sind nicht erlaubt.');
        }

        // Die reine Alphanumerische Zeichenkette (ohne Leerzeichen und Bindestriche)
        $cleanAlphanumeric = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', $plate);

        // Prüfung auf realistische Längen (international)
        if (\strlen($cleanAlphanumeric) < 2 || \strlen($cleanAlphanumeric) > 15) {
            throw new InvalidArgumentException('Das Kennzeichen ist ungültig. Es muss zwischen 2 und 15 Zeichen lang sein.');
        }

        $formatted = $this->format($plate);

        if ($formatted === '') {
            throw new InvalidArgumentException('Das Kennzeichen konnte nicht formatiert werden.');
        }

        $this->value = $formatted;
    }

    /**
     * Returns the formatted license plate.
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Returns an alphanumeric-only representation of the plate.
     * Useful for database searches and normalization.
     */
    public function getCleanForSearch(): string
    {
        return \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', \strtoupper($this->value)) ?? '';
    }

    /**
     * Formats the license plate into a standardized German format if possible.
     */
    private function format(string $plate): string
    {
        // Wenn der Nutzer explizit ein Minus oder Leerzeichen getippt hat,
        // belassen wir das bei Nicht-Standard-Kennzeichen weitgehend so.
        if (\str_contains($plate, '-')) {
            $plate = \preg_replace('/-+/', '-', $plate);
            $plate = \preg_replace('/\s+/', ' ', $plate);

            return (string) \preg_replace('/([A-ZÄÖÜ])(\d)/u', '$1 $2', \trim($plate));
        }

        $val = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', $plate);

        // Versuch: Typisch deutsches Format erkennen (z.B. B-AB 1234)
        if (\preg_match('/^([A-ZÄÖÜ]{3})([A-ZÄÖÜ]{1,2})(\d{1,4}[EH]?)$/u', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }
        if (\preg_match('/^([A-ZÄÖÜ]{1,2})([A-ZÄÖÜ]{1,2})(\d{1,4}[EH]?)$/u', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // Fallback für internationale Kennzeichen (z.B. SB 58 JVR).
        // Überflüssige Leerzeichen werden entfernt, aber die Struktur wird nicht zerstört.
        $originalCleaned = \preg_replace('/\s+/', ' ', $plate);

        return \trim((string) $originalCleaned);
    }
}
