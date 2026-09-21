<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use DateTimeImmutable;

interface MagicLinkRepositoryInterface
{
    public function save(MagicLink $magicLink): void;

    public function findByInput(string $input): ?MagicLink;

    public function delete(string $token): void;

    public function deleteExpired(DateTimeImmutable $now): void;
}
