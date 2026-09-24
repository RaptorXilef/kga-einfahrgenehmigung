<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewDashboard;

/**
 * Enthält die vorausberechneten CSS-Klassen und ARIA-Attribute für die Dashboard-Tabs.
 */
final readonly class DashboardTabStateDto
{
    public function __construct(
        public string $isActiveClass, // 'is-active' oder ''
        public string $ariaSelected,  // 'true' oder 'false'
        public string $tabIndex,      // '0' oder '-1'
        public string $ariaHidden,    // 'false' oder 'true'
    ) {
    }
}
