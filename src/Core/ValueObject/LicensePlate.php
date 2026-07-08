<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * TODO Auf deutsch schreiben
 * Value Object representing a vehicle license plate (Kennzeichen).
 *
 * Enforces standardized formatting, strict character validation,
 * and prevents multiple license plates (fraud prevention).
 */
final readonly class LicensePlate
{
    /**
     * @var string The formatted license plate string.
     */
    public string $value;

    /**
     * @param  string                    $plate The raw license plate input.
     * @throws \InvalidArgumentException If the plate is empty after trimming.
     */
    public function __construct(string $plate)
    {
        $plate = \trim(\strtoupper($plate));

        if ($plate === '') {
            throw new \InvalidArgumentException('Das Kennzeichen darf nicht leer sein.');
        }

        // 1. Zeichen-Validierung (Erlaubt Buchstaben, Zahlen, Leerzeichen, Bindestriche, Umlaute für deutsche Kennzeichen)
        if (! \preg_match('/^[A-ZÄÖÜ0-9\-\s]+$/', $plate)) {
            throw new \InvalidArgumentException('Das Kennzeichen enthält ungültige Zeichen (z.B. Schrägstriche). Es ist nur ein Kennzeichen erlaubt.');
        }

        // Nur alphanumerische Zeichen für die Betrugsprüfung filtern
        $cleanAlphanumeric = \preg_replace('/[^A-ZÄÖÜ0-9]/', '', $plate);

        // 2. Betrugsschutz A: Die physikalische Maximallänge
        // Deutsche Kennzeichen haben max. 8 Zeichen. Wir erlauben 9 für ausländische.
        if (\strlen($cleanAlphanumeric) > 9) {
            throw new \InvalidArgumentException('Das Kennzeichen ist zu lang. Bitte geben Sie strikt nur EIN Kennzeichen ein.');
        }

        // 3. Betrugsschutz B: Die L-N-L-N Muster-Erkennung
        // Erkennt zwei aneinandergereihte Kennzeichen wie "B-A 1 B-B 2" (Buchstaben -> Zahlen -> Buchstaben -> Zahlen)
        if (\preg_match('/[A-ZÄÖÜ]+[0-9]+[A-ZÄÖÜ]+[0-9]+/', $cleanAlphanumeric)
            || \preg_match('/[0-9]+[A-ZÄÖÜ]+[0-9]+[A-ZÄÖÜ]+/', $cleanAlphanumeric)) {
            throw new \InvalidArgumentException('Eingabe abgelehnt: Die Struktur deutet auf mehrere Kennzeichen hin. Es ist nur ein Fahrzeug erlaubt.');
        }

        $formatted   = $this->format($plate);
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
        return \preg_replace('/[^A-ZÄÖÜ0-9]/', '', \strtoupper($this->value)) ?? '';
    }

    /**
     * Formats the license plate into a standardized German format if possible.
     */
    private function format(string $plate): string
    {
        // 1. Wenn schon ein Bindestrich existiert, nur das Leerzeichen vor der Zahl sicherstellen
        if (\str_contains($plate, '-')) {
            return (string) \preg_replace('/([A-ZÄÖÜ])(\d)/', '$1 $2', $plate);
        }

        // 2. Komplettreinigung für die Automatik
        $val = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/', '', $plate);

        // 3. Sonderfall: 4 Buchstaben am Anfang (z.B. BBDW123E -> BB-DW 123E)
        // Automatische Formatierung für typische deutsche Kennzeichen (inkl. Umlaute für Städte wie WÜ, MÜ, TÖL)
        if (\preg_match('/^([A-ZÄÖÜ]{2})([A-Z]{2})(\d{1,4}[E|H]?)$/', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // 4. Berlin-Priorität (B-XX 1234E)
        if (\preg_match('/^(B)([A-Z]{1,2})(\d{1,4}[E|H]?)$/', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // 5. Standard: 1-3 Buchstaben + 1-2 Buchstaben + Zahlen (+E/H)
        if (\preg_match('/^([A-ZÄÖÜ]{1,3})([A-Z]{1,2})(\d{1,4}[E|H]?)$/', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // 6. Fallback
        return (string) \preg_replace('/^([A-ZÄÖÜ]{1,3})(\d{1,4}[E|H]?)$/', '$1 $2', $val);
    }
}
