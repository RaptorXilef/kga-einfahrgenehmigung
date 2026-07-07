<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * Value Object representing a valid email address.
 *
 * Enforces strict email format validation and immutability.
 */
final readonly class EmailAddress
{
    /**
     * @var string The normalized, lowercase email address.
     */
    public string $value;

    /**
     * @param  string                    $value The raw email address string.
     * @throws \InvalidArgumentException If the email is empty or invalid.
     */
    public function __construct(string $value)
    {
        $value = \trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('E-Mail-Adresse darf nicht leer sein.');
        }

        if (! \filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Ungültiges E-Mail-Format: {$value}");
        }

        $this->value = \strtolower($value);
    }

    /**
     * Returns the email address as a string.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}
