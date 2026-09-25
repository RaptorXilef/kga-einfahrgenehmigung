<?php

declare(strict_types=1);

namespace App\Modules\Finance\Application\DTO;

/**
 * Striktes DTO für den Bank-Import, das rohe Arrays ablöst.
 */
final readonly class UnpaidPermitsDto
{
    public function __construct(
        /**
         * @var array<string, bool>
         */
        public array $allCodes,
        /**
         * @var array<string, string>
         */
        public array $unpaidCodes,
        /**
         * @var array<string, string>
         */
        public array $unpaidPlates,
        /**
         * @var array<string, float>
         */
        public array $prices,
    ) {
    }
}
