<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SearchPermits;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\Permit;
use App\SharedKernel\Application\Query\QueryHandlerInterface;

/**
 * @implements QueryHandlerInterface<SearchPermitsQuery, array>
 */
final readonly class SearchPermitsHandler implements QueryHandlerInterface
{
    public function __construct(
        private StorageInterface $storage,
        private PermitArchiveRepositoryInterface $archiveRepository,
        private ClockInterface $clock,
        private ConfigInterface $config,
    ) {
    }

    public function handle(mixed $query): array
    {
        $allActive = $this->storage->getAll();
        $archived = [];

        if (\in_array($query->tab, ['all', 'archive'], true)) {
            $archived = $this->archiveRepository->getArchivedPermits(1970);
        }

        $combined = \array_merge($allActive, $archived);
        $filtered = [];
        $queryLower = \strtolower($query->query);
        $now = $this->clock->now();
        $permitTemplates = $this->config->get('permit_templates', []);

        foreach ($combined as $permit) {
            if ($query->templateType !== 'all') {
                $tplType = $permitTemplates[$permit->template_key->value]['type'] ?? 'standard';
                if ($tplType !== $query->templateType) {
                    continue;
                }
            }

            $isArchived = $this->archiveRepository->isCodeInArchive($permit->code->value);
            $isExpired = $permit->isExpired($now);

            if ($query->tab === 'active' && ($isArchived || $isExpired)) {
                continue;
            }
            if ($query->tab === 'expired' && (!$isExpired || $isArchived)) {
                continue;
            }
            if ($query->tab === 'archive' && !$isArchived) {
                continue;
            }
            if (!$permit->matchesSearch($queryLower)) {
                continue;
            }

            $filtered[] = $permit;
        }

        \usort($filtered, fn ($a, $b): int => $b->getCreatedAt() <=> $a->getCreatedAt());

        $total = \count($filtered);
        $offset = ($query->page - 1) * $query->limit;
        $items = \array_slice($filtered, $offset, $query->limit);

        $formattedItems = \array_map(fn (Permit $permit): array => [
            'bis' => $permit->getValidUntil()->format('d.m.Y'),
            'code' => $permit->code->value,
            'email' => $permit->getOwnerEmail(),
            'erstellt' => $permit->getCreatedAt()->format('d.m.Y H:i'),
            'is_archived' => $this->archiveRepository->isCodeInArchive($permit->code->value),
            'kennzeichen' => $permit->getLicensePlate(),
            'name' => $permit->getOwnerName(),
            'parzelle' => $permit->getPlotNumber(),
            'preis' => $permit->getPrice(),
            'status' => $permit->getStatus()->value,
            'template_key' => $permit->template_key->value,
            'von' => $permit->getValidFrom()->format('d.m.Y'),
            'zweck' => $permit->getPurpose(),
        ], $items);

        return ['items' => $formattedItems, 'total' => $total];
    }
}
