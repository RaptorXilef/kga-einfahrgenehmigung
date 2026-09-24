<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

/**
 * DTO für das Select-Feld der Paginierungs-Limits in der Control-Bar.
 */
final readonly class LimitOptionDto
{
    public function __construct(
        public int $value,
        public string $selectedAttr, // 'selected' oder ''
    ) {
    }
}
