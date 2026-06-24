<?php

declare(strict_types=1);

namespace App\Core\Event;

final readonly class GroupDeletedEvent
{
    public function __construct(public string $groupId)
    {
    }
}
