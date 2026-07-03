<?php

declare(strict_types=1);

namespace App\Contracts\Storage;

use App\Core\Entity\AuditLog;

interface AuditLogRepositoryInterface
{
    public function save(AuditLog $log): void;

    /**
     * Gibt ein Array zurück: ['items' => AuditLog[], 'total' => int]
     */
    public function getPaginated(int $page, int $limit, string $actionFilter = ''): array;
}
