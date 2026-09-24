<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

use InvalidArgumentException;
use Override;
use Stringable;

/**
 * Value Object für eine KGA Parzellennummer.
 * Darf ausschließlich eine Zahl sein (bis max. 4 Stellen, abhängig von der Config).
 */
final readonly class PlotNumber implements Stringable
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
        if (\is_string($value)) {
            $value = \trim($value);
            if ($value === '') {
                throw new InvalidArgumentException('Die Parzellennummer darf nicht leer sein.');
            }

            if (!\ctype_digit($value)) {
                throw new InvalidArgumentException('Fehler: Die Parzellennummer darf ausschließlich aus Zahlen bestehen.');
            }
        }

        $intVal = (int) $value;

        // FIX: Erlaubt >= 0. (0 wird für DSGVO-Anonymisierung zwingend benötigt!)
        if ($intVal < 0 || $intVal > $maxPlotNumber) {
            throw new InvalidArgumentException("Die Parzellennummer muss zwischen 1 und {$maxPlotNumber} liegen.");
        }

        $this->value = $intVal;
    }

    /**
     * Formatiert die Parzelle immer 4-stellig (z. B. '0020').
     */
    public function getFormatted(): string
    {
        return \str_pad((string) $this->value, 4, '0', \STR_PAD_LEFT);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->getFormatted();
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
