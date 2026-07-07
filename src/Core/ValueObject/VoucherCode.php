<?php

declare(strict_types=1);

namespace App\Core\ValueObject;

/**
 * Value Object representing a discount or access voucher code.
 */
final readonly class VoucherCode
{
    /**
     * @var string The normalized voucher code.
     */
    public string $value;

    /**
     * @param  string                    $value The raw voucher code.
     * @throws \InvalidArgumentException If the voucher code is empty.
     */
    public function __construct(string $value)
    {
        $value = \trim($value);

        if ($value === '') {
            throw new \InvalidArgumentException('Der Gutscheincode darf nicht leer sein.');
        }

        $this->value = \strtoupper($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
