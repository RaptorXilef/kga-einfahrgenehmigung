<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

use InvalidArgumentException;
use Stringable;

/**
 * Value Object für eine E-Mail-Adresse.
 */
final readonly class EmailAddress implements Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $val = \trim($value);
        if (!\filter_var($val, \FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Ungültige E-Mail-Adresse: '{$val}'.");
        }

        $this->value = \strtolower($val);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    // FIX: Explizite __toString Methode, damit PHP das Objekt beim "Verketten"
    // nicht versehentlich ablehnt.
    public function __toString(): string
    {
        return $this->value;
    }
}
