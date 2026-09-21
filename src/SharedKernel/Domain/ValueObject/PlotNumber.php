<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object für eine KGA Parzellennummer.
 * Darf ausschließlich eine Zahl sein (bis max. 4 Stellen, abhängig von der Config).
 */
final readonly class PlotNumber
{
    public int $value;

    /**
     * @param int|string $value Die eingegebene Parzellennummer.
     * @param int $maxPlotNumber Das Limit aus der Config (Standard 9999 für max. 4 Stellen).
     *
     * @throws InvalidArgumentException Wenn die Eingabe keine Zahl ist oder das Limit überschreitet.
     */
    public function __construct(int|string $value, int $maxPlotNumber = 9999)
    {
        if (!\is_numeric($value)) {
            throw new InvalidArgumentException('Die Parzelle muss eine reine Zahl sein.');
        }

        $val = (int) $value;

        // Wir nehmen an, dass Parzelle 0 nicht existiert.
        if ($val < 1 || $val > $maxPlotNumber) {
            throw new InvalidArgumentException("Die Parzellennummer muss zwischen 1 und {$maxPlotNumber} liegen.");
        }

        $this->value = $val;
    }

    /**
     * Formatiert die Parzelle immer 4-stellig (z. B. '0020').
     */
    public function getFormatted(): string
    {
        return \str_pad((string) $this->value, 4, '0', \STR_PAD_LEFT);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
