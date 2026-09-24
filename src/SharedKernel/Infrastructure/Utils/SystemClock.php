<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Utils;

use App\Contracts\Utils\ClockInterface;
use DateTimeImmutable;
use Override;

/**
 * Produktiv-Implementierung der Systemuhr auf Basis von DateTimeImmutable.
 */
final class SystemClock implements ClockInterface
{
    #[Override]
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    #[Override]
    public function nowAsString(): string
    {
        return (new DateTimeImmutable())->format('Y-m-d H:i:s');
    }
}
