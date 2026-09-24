<?php

declare(strict_types=1);

namespace App\Contracts\System;

interface SystemInfoInterface
{
    public function getChangelog(): string;

    public function getCurrentVersion(): string;

    /**
     * @return array<int, array{version: string, content: string, clean_version: string}>
     */
    public function getAllReleaseNotes(): array;

    /**
     * @return array<int, array{version: string, content: string, clean_version: string}>
     */
    public function getUnreadReleaseNotes(string $lastSeenVersion): array;
}
