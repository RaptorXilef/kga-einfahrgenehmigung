<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * TODO Auf deutsch schreiben
 * Value Object representing a vehicle license plate (Kennzeichen).
 *
 * Enforces standardized formatting and strict structural validation.
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

        // 1. Basis-Zeichen-Validierung (Buchstaben, Zahlen, Leerzeichen, Bindestriche, Umlaute)
        if (! \preg_match('/^[A-ZÄÖÜ0-9\-\s]+$/u', $plate)) {
            throw new \InvalidArgumentException('Bitte geben Sie nur ein gültiges Kennzeichen ein (Buchstaben, Zahlen, Leerzeichen, Bindestrich). Sonderzeichen wie / sind nicht erlaubt.');
        }

        // Nur alphanumerische Zeichen für den Bauplan filtern
        $cleanAlphanumeric = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', $plate);

        // 2. Der Bauplan (Strikte Struktur-Prüfung für deutsche Kennzeichen)
        // Regel: 1 bis 5 Buchstaben, gefolgt von 1 bis 4 Zahlen, optional 'E' oder 'H' am Ende.
        // Diese eine Regel blockiert automatisch: 5-stellige Zahlen, Doppel-Kennzeichen und reine Text-Eingaben!
        if (! \preg_match('/^[A-ZÄÖÜ]{1,5}[0-9]{1,4}[EH]?$/u', $cleanAlphanumeric)) {
            throw new \InvalidArgumentException('Das Kennzeichen ist ungültig. Ein reguläres Kennzeichen hat maximal 4 Ziffern und folgt dem Format "B-AB 1234".');
        }

        $formatted = $this->format($plate);

        if ($formatted === '') {
            throw new \InvalidArgumentException('Das Kennzeichen konnte nicht formatiert werden.');
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
        // 1. Wenn der Nutzer die Bindestriche selbst setzt, vertrauen wir seiner Formatierung
        if (\str_contains($plate, '-')) {
            $plate = \preg_replace('/-+/', '-', $plate); // Doppelte Minus bereinigen
            $plate = \preg_replace('/\s+/', ' ', $plate); // Doppelte Leerzeichen bereinigen

            // Stellt sicher, dass zwischen dem letzten Buchstaben und der ersten Ziffer ein Leerzeichen ist
            return (string) \preg_replace('/([A-ZÄÖÜ])(\d)/u', '$1 $2', \trim($plate));
        }

        // 2. Komplettreinigung für die Automatik
        $val = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', $plate);

        // 3. Sonderfall: 4 Buchstaben am Anfang (z.B. BBDW123E -> BB-DW 123E)
        // Automatische Formatierung (Fallback, wenn vom Nutzer kein Minus eingegeben wurde)
        // 4 oder 5 Buchstaben (z.B. SHA-AA)
        if (\preg_match('/^([A-ZÄÖÜ]{3})([A-ZÄÖÜ]{1,2})(\d{1,4}[EH]?)$/u', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // 5. Standard: 1-3 Buchstaben + 1-2 Buchstaben + Zahlen (+E/H)
        // 2 oder 3 Buchstaben (z.B. B-AB, WÜ-A)
        if (\preg_match('/^([A-ZÄÖÜ]{1,2})([A-ZÄÖÜ]{1,2})(\d{1,4}[EH]?)$/u', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // 6. Fallback
        return (string) \preg_replace('/^([A-ZÄÖÜ]{1,3})(\d{1,4}[EH]?)$/u', '$1 $2', $val);
    }
}
