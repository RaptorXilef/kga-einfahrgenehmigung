<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * Value Object representing an IPv4 or IPv6 address.
 *
 * Enforces valid IP formats using PHP's native filter.
 */
final readonly class IpAddress
{
    /**
     * @var string The validated IP address.
     */
    public string $value;

    /**
     * @param  string                    $value The raw IP address string.
     * @throws \InvalidArgumentException If the IP is empty or invalid.
     */
    public function __construct(string $value)
    {
        $value = \trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('IP-Adresse darf nicht leer sein.');
        }

        if (! \filter_var($value, \FILTER_VALIDATE_IP)) {
            throw new \InvalidArgumentException("Ungültiges IP-Adressen-Format: {$value}");
        }

        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
