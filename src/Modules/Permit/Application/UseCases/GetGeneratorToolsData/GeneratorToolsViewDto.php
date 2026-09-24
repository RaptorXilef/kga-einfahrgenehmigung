<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\GetGeneratorToolsData;

/**
 * 100% logikfreies DTO für den Tab "Ausstellung" (Genehmigungen & Gutscheine).
 */
final readonly class GeneratorToolsViewDto
{
    public function __construct(
        public bool $hasAnyTemplate,
        public array $allowedTemplateOptions,
        public array $vehicleOptions,
        public array $standardPurposesOptions,
        public array $customPurposes,
        public array $voucherReasons,
        public string $defaultDateVon,
        public string $tplMetadataJson,
    ) {
    }
}
