<?php

declare(strict_types=1);

namespace App\Application\View;

/**
 * Repräsentiert ein einzelnes Element in der Paginierungs-Leiste (Seitenzahl, aktive Seite oder Auslassungspunkte).
 */
final readonly class PaginationPageItemDto
{
    public function __construct(
        public string $label,
        public string $url,
        public bool $isCurrent,
        public bool $isEllipsis,
    ) {
    }
}
