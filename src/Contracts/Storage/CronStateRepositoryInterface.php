<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

interface CronStateRepositoryInterface
{
    public function getLastRunTime(): int;

    public function setLastRunTime(int $timestamp): void;
}
