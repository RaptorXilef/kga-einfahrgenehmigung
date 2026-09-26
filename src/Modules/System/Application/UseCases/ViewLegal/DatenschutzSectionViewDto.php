<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewLegal;

/**
 * 100% logikfreies View-DTO für einen einzelnen Abschnitt der Datenschutzerklärung.
 */
final readonly class DatenschutzSectionViewDto
{
    public function __construct(
        public string $title,
        public string $text,
    ) {
    }
}
