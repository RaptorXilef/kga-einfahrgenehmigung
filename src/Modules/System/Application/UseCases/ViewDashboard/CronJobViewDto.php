<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

/**
 * 100% logikfreies View-DTO für einen Cronjob-Eintrag im System-Tab.
 */
final readonly class CronJobViewDto
{
    public function __construct(
        public string $label,
        public string $description,
        public string $url,
        public string $interval,
        public string $iconUrl,
    ) {
    }
}
