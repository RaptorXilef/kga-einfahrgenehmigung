<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

// TODO DocBlock
interface MailQueueRepositoryInterface
{
    public function enqueue(string $recipient, string $subject, string $template, array $data): void;

    public function processBatch(int $limit, callable $processor): int;
}
