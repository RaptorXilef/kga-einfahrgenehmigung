<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitFilterService
{
    public function __construct(
        private ConfigInterface $config,
        private StorageInterface $storage,
    ) {
    }

    public function getFilteredPermits(string $filterStart, string $filterEnd, string $filterType, string $searchQuery): array
    {
        $allPermits      = $this->storage->getAll();
        $permitTemplates = $this->config->get('permit_templates', []);
        $queryLower      = \strtolower(\trim($searchQuery));

        return \array_filter($allPermits, function (Permit $permit) use ($filterStart, $filterEnd, $filterType, $permitTemplates, $queryLower): bool {
            $date = $permit->getCreatedAt()->format('Y-m-d');
            if ($date < $filterStart || $date > $filterEnd) {
                return false;
            }

            if ($filterType !== 'all') {
                $tplType = $permitTemplates[$permit->template_key]['type'] ?? 'standard';
                if ($tplType !== $filterType) {
                    return false;
                }
            }

            if ($queryLower !== '') {
                return $permit->matchesSearch($queryLower);
            }

            return true;
        });
    }
}
