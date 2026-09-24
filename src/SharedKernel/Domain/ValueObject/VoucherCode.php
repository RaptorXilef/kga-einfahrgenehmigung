<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

use InvalidArgumentException;
use Override;
use Stringable;

/**
 * Value Object representing a discount or access voucher code.
 */
final readonly class VoucherCode implements Stringable
{
    public string $value;

    public function __construct(string $value)
    {
        $value = \trim($value);
        if ($value === '') {
            throw new InvalidArgumentException('Der Gutscheincode darf nicht leer sein.');
        }
        $this->value = \strtoupper($value);
    }

    #[Override]
    public function __toString(): string
    {
        return $this->value;
    }
}
