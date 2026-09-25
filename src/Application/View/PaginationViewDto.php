<?php

declare(strict_types=1);

namespace App\Application\View;

/**
 * 100% logikfreies View-DTO für die Paginierungs-Komponente (partials/admin/pagination.phtml).
 * Kapselt die gesamte URL-Query-Bereinigung und Nachbarschafts-Mathematik.
 */
final readonly class PaginationViewDto
{
    /**
     * @param PaginationPageItemDto[] $items
     */
    public function __construct(
        public bool $hasMultiplePages,
        public bool $hasEntries,
        public int $totalCount,
        public int $startEntry,
        public int $endEntry,
        public bool $hasPrev,
        public string $prevUrl,
        public bool $hasNext,
        public string $nextUrl,
        public array $items,
    ) {
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    public static function fromParameters(
        int $page,
        int $totalCount,
        int $limit,
        array $queryParams,
        string $paginationParam = 'page',
        ?string $tabFocusId = null,
    ): self {
        $safeLimit = \max(1, $limit);
        $totalPages = \max(1, (int) \ceil($totalCount / $safeLimit));
        $currentPage = \max(1, \min($page, $totalPages));
        $offset = ($currentPage - 1) * $safeLimit;

        $startEntry = $totalCount > 0 ? $offset + 1 : 0;
        $endEntry = \min($offset + $safeLimit, $totalCount);

        if ($totalPages <= 1) {
            return new self(
                hasMultiplePages: false,
                hasEntries: $totalCount > 0,
                totalCount: $totalCount,
                startEntry: $startEntry,
                endEntry: $endEntry,
                hasPrev: false,
                prevUrl: '',
                hasNext: false,
                nextUrl: '',
                items: [],
            );
        }

        $params = $queryParams;
        unset($params['page'], $params['audit_page']);

        if ($tabFocusId !== null && !\in_array($tabFocusId, ['tab-expired', 'tab-stats'], true)) {
            unset($params['archive_depth']);
        }

        if ($tabFocusId !== null) {
            $params['focus'] = $tabFocusId;
        } else {
            unset($params['focus']);
        }

        $queryString = \http_build_query($params);
        $baseUrlStr = '?' . ($queryString !== '' ? $queryString . '&' : '') . $paginationParam . '=';

        $adjacents = 2;
        $rawPages = [];

        if ($totalPages <= (1 + ($adjacents * 2) + 2)) {
            for ($i = 1; $i <= $totalPages; ++$i) {
                $rawPages[] = $i;
            }
        } elseif ($currentPage <= $adjacents + 2) {
            for ($i = 1; $i <= 1 + ($adjacents * 2); ++$i) {
                $rawPages[] = $i;
            }
            $rawPages[] = '...';
            $rawPages[] = $totalPages;
        } elseif ($totalPages - $currentPage <= $adjacents + 1) {
            $rawPages[] = 1;
            $rawPages[] = '...';
            for ($i = $totalPages - ($adjacents * 2); $i <= $totalPages; ++$i) {
                $rawPages[] = $i;
            }
        } else {
            $rawPages[] = 1;
            $rawPages[] = '...';
            for ($i = $currentPage - $adjacents; $i <= $currentPage + $adjacents; ++$i) {
                $rawPages[] = $i;
            }
            $rawPages[] = '...';
            $rawPages[] = $totalPages;
        }

        $items = [];
        foreach ($rawPages as $p) {
            if ($p === '...') {
                $items[] = new PaginationPageItemDto(
                    label: '...',
                    url: '',
                    isCurrent: false,
                    isEllipsis: true,
                );
            } else {
                $pageNum = (int) $p;
                $items[] = new PaginationPageItemDto(
                    label: (string) $pageNum,
                    url: $baseUrlStr . $pageNum,
                    isCurrent: $pageNum === $currentPage,
                    isEllipsis: false,
                );
            }
        }

        return new self(
            hasMultiplePages: true,
            hasEntries: $totalCount > 0,
            totalCount: $totalCount,
            startEntry: $startEntry,
            endEntry: $endEntry,
            hasPrev: $currentPage > 1,
            prevUrl: $currentPage > 1 ? $baseUrlStr . ($currentPage - 1) : '',
            hasNext: $currentPage < $totalPages,
            nextUrl: $currentPage < $totalPages ? $baseUrlStr . ($currentPage + 1) : '',
            items: $items,
        );
    }
}
