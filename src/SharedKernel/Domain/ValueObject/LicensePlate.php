<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

use InvalidArgumentException;
use Override;
use Stringable;

/**
 * Value Object für ein Kfz-Kennzeichen.
 * Erzwingt standardisierte Formatierung und strikte strukturelle Validierung.
 */
final readonly class LicensePlate implements Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $plate = \strtoupper(\trim($value));

        if ($plate === '') {
            throw new InvalidArgumentException('Das Kennzeichen darf nicht leer sein.');
        }

        // Erlaube Platzhalter für "Kein Kennzeichen" (Sharing/LKW) und Anonymisierung
        if (\in_array($plate, ['---', 'XXX-XX 9999'], true)) {
            $this->value = $plate;

            return;
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

        // Plausibilitäts-Check: Muss mindestens einen Buchstaben und eine Zahl enthalten (International & E-Scooter tauglich)
        if (!\preg_match('/[A-ZÄÖÜ]/u', $cleanAlphanumeric) || !\preg_match('/[0-9]/', $cleanAlphanumeric)) {
            throw new InvalidArgumentException('Bitte geben Sie ein plausibles Kfz-Kennzeichen ein (es muss Buchstaben und Zahlen enthalten).');
        }

        $formatted = $this->format($plate);

        if ($formatted === '') {
            throw new InvalidArgumentException('Das Kennzeichen konnte nicht formatiert werden.');
        }

        $this->value = $formatted;
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Gibt das Kennzeichen für die Suche normalisiert zurück (ohne Leerzeichen/Sonderzeichen).
     */
    public function getNormalized(): string
    {
        $normalized = \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', $this->value);

        return $normalized ?? $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->getNormalized() === $other->getNormalized();
    }

    /**
     * Formatiert das Kennzeichen nach typisch deutschem Standard, falls möglich.
     */
    private function format(string $plate): string
    {
        // Wenn der Nutzer explizit ein Minus oder Leerzeichen getippt hat,
        // belassen wir das bei Nicht-Standard-Kennzeichen weitgehend so.
        if (\str_contains($plate, '-')) {
            $plate = \preg_replace('/-+/', '-', $plate);
            $plate = \preg_replace('/\s+/', ' ', (string) $plate);

            return (string) \preg_replace('/([A-ZÄÖÜ])(\d)/u', '$1 $2', \trim((string) $plate));
        }

        $val = (string) \preg_replace('/[^A-ZÄÖÜ0-9]/u', '', $plate);

        // Prio 1: Berlin-Regel, um Konflikte bei z.B. BAB1234 (Bamberg vs. Berlin) zu lösen
        if (\preg_match('/^(B)([A-ZÄÖÜ]{1,2})(\d{1,4}[EH]?)$/u', $val, $matches)) {
            return "{$matches[1]}-{$matches[2]} {$matches[3]}";
        }

        // Prio 2: Typisch deutsches Format erkennen
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
