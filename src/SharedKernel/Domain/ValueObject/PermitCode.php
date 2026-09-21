<?php

declare(strict_types=1);

namespace App\SharedKernel\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Value Object für den eindeutigen Genehmigungscode.
 */
final readonly class PermitCode
{
    public string $value;

    public function __construct(string $value)
    {
        $val = \trim($value);

        if ($val === '') {
            throw new InvalidArgumentException('Der Permit-Code darf nicht leer sein.');
        }

        $this->value = \strtoupper($val);
    }
}
