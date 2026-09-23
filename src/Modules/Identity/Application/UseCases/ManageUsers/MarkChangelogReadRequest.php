<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;

final readonly class MarkChangelogReadRequest
{
    private function __construct(public string $version)
    {
    }

    public static function fromArray(array $post): self
    {
        $version = \trim((string) ($post['version'] ?? ''));
        if ($version === '') {
            throw ValidationException::withMessage('Keine Version übergeben.');
        }

        return new self($version);
    }
}
