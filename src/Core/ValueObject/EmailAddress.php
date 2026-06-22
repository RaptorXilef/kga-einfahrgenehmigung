<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class EmailAddress
{
    public string $value;

    public function __construct(string $value)
    {
        $value = \trim($value);
        if ($value !== '' && ! \filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Ungültiges E-Mail-Format: {$value}");
        }
        $this->value = \strtolower($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }
}
