<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object für eine E-Mail-Adresse.
 */
final readonly class EmailAddress
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
}
