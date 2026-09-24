<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

/**
 * DTO für die Such- und Filterleiste (Control Bar).
 */
final readonly class ControlBarViewDto
{
    public function __construct(
        public string $startValue,
        public string $endValue,
        public string $startValueFormatted,
        public string $endValueFormatted,
        public string $typeSelectAll,       // 'selected' oder ''
        public string $typeSelectStandard,  // 'selected' oder ''
        public string $typeSelectPermanent, // 'selected' oder ''
        /**
         * @var LimitOptionDto[]
         */
        public array $limitOptions,
        public string $searchValue,
        public bool $showResetButton,
    ) {
    }
}
