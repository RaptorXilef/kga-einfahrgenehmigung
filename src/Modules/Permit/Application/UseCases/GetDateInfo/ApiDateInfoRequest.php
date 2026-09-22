<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDateInfo;

use DateTimeImmutable;
use Exception;

final readonly class ApiDateInfoRequest
{
    private function __construct(public DateTimeImmutable $von, public DateTimeImmutable $bis)
    {
    }

    public static function fromArray(array $input): self
    {
        $vonStr = (string) ($input['von'] ?? 'today');
        $bisStr = (string) ($input['bis'] ?? 'today');

        try {
            $von = new DateTimeImmutable($vonStr);
        } catch (Exception) {
            $von = new DateTimeImmutable('today');
        }

        try {
            $bis = new DateTimeImmutable($bisStr);
        } catch (Exception) {
            $bis = new DateTimeImmutable('today');
        }

        return new self($von, $bis);
    }
}
