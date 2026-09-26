<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ViewLegal;

/**
 * 100% logikfreies View-DTO für das Impressum (templates/pages/frontend/impressum.phtml).
 */
final readonly class ImpressumViewDto
{
    /**
     * @param string[] $boardMembers
     */
    public function __construct(
        public string $title,
        public string $clubName,
        public string $address,
        public array $boardMembers,
        public string $phone,
        public string $email,
        public string $registerCourt,
        public string $registerNumber,
        public bool $hasUstId,
        public string $ustId,
        public string $responsiblePersonName,
        public string $responsiblePersonAddress,
    ) {
    }
}
