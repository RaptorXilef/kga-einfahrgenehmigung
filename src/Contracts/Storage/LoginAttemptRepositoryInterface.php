<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\LoginAttempt;

/**
 * TODO
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface LoginAttemptRepositoryInterface
{
    public function findByIp(string $ip): ?LoginAttempt;

    public function save(LoginAttempt $attempt): void;

    public function deleteByIp(string $ip): void;

    public function deleteOlderThan(int $minutes): void;

    public function import(array $data): void;
}
