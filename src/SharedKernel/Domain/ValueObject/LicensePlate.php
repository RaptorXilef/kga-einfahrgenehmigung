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

        // Optional: Weitere Regex-Validierung (z.B. deutsche Kennzeichen)
        // Für den Anfang belasse ich es beim Legacy-Verhalten (darf nicht leer sein).
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
