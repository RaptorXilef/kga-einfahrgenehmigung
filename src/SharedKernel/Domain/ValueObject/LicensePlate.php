<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object für ein Kfz-Kennzeichen.
 */
final readonly class LicensePlate
{
    public string $value;

    public function __construct(string $value)
    {
        $val = \strtoupper(\trim($value));

        if ($val === '') {
            throw new InvalidArgumentException('Das Kennzeichen darf nicht leer sein.');
        }

        // Erlaube Platzhalter für "Kein Kennzeichen" (Sharing/LKW) und Anonymisierung
        if (\in_array($val, ['---', 'XXX-XX 9999'], true)) {
            $this->value = $val;

            return;
        }

        // Strikte Validierung auf ein plausibles Kennzeichen (Verhindert "XXX" oder "keins").
        // Muss mindestens einen Buchstaben und eine Zahl enthalten (International & E-Scooter tauglich).
        if (!\preg_match('/^(?=.*[A-ZÄÖÜ])(?=.*[0-9])[A-ZÄÖÜ0-9 \-]{3,15}$/u', $val)) {
            throw new InvalidArgumentException('Bitte geben Sie ein plausibles Kfz-Kennzeichen ein (z.B. B-XX 123).');
        }

        $this->value = $val;
    }

    /**
     * Gibt das Kennzeichen für die Suche normalisiert zurück (ohne Leerzeichen/Sonderzeichen).
     */
    public function getNormalized(): string
    {
        $normalized = \preg_replace('/[^A-Z0-9]/', '', $this->value);

        return $normalized ?? $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->getNormalized() === $other->getNormalized();
    }
}
