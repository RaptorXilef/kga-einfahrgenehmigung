<?php

declare(strict_types=1);

namespace App\Modules\System\Domain;

interface MailQueueRepositoryInterface
{
    public function enqueue(MailJob $job): void;

    public function processBatch(int $limit, callable$processor): int;
}
