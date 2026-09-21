<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object für einen monetären Preis (in Euro).
 * Verhindert negative Preise und kapselt die Formatierungslogik.
 */
final readonly class Price
{
    public function __construct(
        public float $amount,
    ) {
        if ($this->amount < 0.0) {
            throw new InvalidArgumentException('Ein Preis darf nicht negativ sein.');
        }
    }

    public function isFree(): bool
    {
        return $this->amount <= 0.001; // Float-Toleranz
    }

    public function getFormatted(): string
    {
        return \number_format($this->amount, 2, ',', '.') . ' €';
    }

    public function equals(self $other): bool
    {
        return \abs($this->amount - $other->amount) < 0.001;
    }
}
