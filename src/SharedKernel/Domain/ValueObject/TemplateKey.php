<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object für Tarif- bzw. Vorlagenschlüssel.
 */
final readonly class TemplateKey
{
    public string $value;

    public function __construct(string $value)
    {
        $val = \trim($value);
        // Self-Healing für alte Datenbank-Einträge
        $val = \str_replace('.', '_', $val);

        if ($val === '') {
            throw new InvalidArgumentException('Der Template-Key darf nicht leer sein.');
        }

        if (!\preg_match('/^[a-zA-Z0-9_\-]+$/', $val)) {
            throw new InvalidArgumentException("Ungültiges Format für Template-Key: {$val}");
        }

        $this->value = \strtolower($val);
    }
}
