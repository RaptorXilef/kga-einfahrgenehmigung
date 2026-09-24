<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetDateInfo;

use App\Contracts\Utils\ClockInterface;
use DateTimeImmutable;
use Exception;

final readonly class ApiDateInfoRequest
{
    private function __construct(public DateTimeImmutable $von, public DateTimeImmutable $bis)
    {
    }

    public static function fromArray(array $input, ClockInterface $clock): self
    {
        $today = $clock->now()->setTime(0, 0, 0);
        $vonStr = (string) ($input['von'] ?? '');
        $bisStr = (string) ($input['bis'] ?? '');

        try {
            $von = $vonStr !== '' ? new DateTimeImmutable($vonStr) : $today;
        } catch (Exception) {
            $von = $today;
        }

        try {
            $bis = $bisStr !== '' ? new DateTimeImmutable($bisStr) : $today;
        } catch (Exception) {
            $bis = $today;
        }

        return new self($von, $bis);
    }
}
