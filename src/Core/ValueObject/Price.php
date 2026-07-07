<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * Value Object representing a monetary price.
 *
 * Ensures prices are never negative.
 */
final readonly class Price
{
    /**
     * @var float The price value.
     */
    public float $value;

    /**
     * @param  float                     $value The raw price (e.g. from DB or form).
     * @throws \InvalidArgumentException If the price is below 0.
     */
    public function __construct(float $value)
    {
        if ($value < 0.0) {
            throw new \InvalidArgumentException("Der Preis darf nicht negativ sein: {$value}");
        }

        // Optional: Hier könnte man auf 2 Nachkommastellen runden,
        // um Floating-Point-Ungenauigkeiten früh abzufangen.
        $this->value = \round($value, 2);
    }

    /**
     * Formats the price safely as a string (e.g., for APIs or DBs).
     */
    public function __toString(): string
    {
        return \number_format($this->value, 2, '.', '');
    }
}
