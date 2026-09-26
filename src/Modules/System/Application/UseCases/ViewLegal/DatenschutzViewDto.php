<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewLegal;

/**
 * 100% logikfreies View-DTO für die Datenschutzerklärung (templates/pages/frontend/datenschutz.phtml).
 */
final readonly class DatenschutzViewDto
{
    /**
     * @param DatenschutzSectionViewDto[] $sections
     */
    public function __construct(
        public string $title,
        public string $lastUpdated,
        public string $responsibleName,
        public string $responsibleAddress,
        public string $responsibleEmail,
        public string $authorityName,
        public string $authorityAddress,
        public array $sections,
    ) {
    }
}
