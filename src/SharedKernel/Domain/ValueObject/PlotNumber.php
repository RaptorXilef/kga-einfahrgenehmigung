<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object für eine KGA Parzellennummer.
 */
final readonly class PlotNumber
{
    public string $value;

    public function __construct(string $value)
    {
        $val = \trim($value);

        if ($val === '' || !\preg_match('/^[a-zA-Z0-9\-\/]+$/', $val)) {
            throw new InvalidArgumentException("Ungültiges Format für Parzelle: '{$val}'. Erlaubt sind Ziffern, Buchstaben, Binde- und Schrägstrich.");
        }

        $this->value = $val;
    }

    /**
     * Formatiert die Parzelle (z.B. mit führenden Nullen, falls das in der KGA üblich ist).
     */
    public function getFormatted(): string
    {
        // Wenn es eine reine Zahl ist, z.B. 4-stellig auffüllen (0020)
        if (\is_numeric($this->value)) {
            return \str_pad($this->value, 4, '0', \STR_PAD_LEFT);
        }

        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
