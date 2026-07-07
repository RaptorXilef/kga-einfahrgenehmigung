<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * Value Object representing a unique permit identifier (Einfahrgenehmigungscode).
 */
final readonly class PermitCode
{
    /**
     * @var string The normalized permit code.
     */
    public string $value;

    /**
     * @param  string                    $value The raw permit code.
     * @throws \InvalidArgumentException If the code is empty.
     */
    public function __construct(string $value)
    {
        $value = \trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Der Permit-Code darf nicht leer sein.');
        }

        $this->value = \strtoupper($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
