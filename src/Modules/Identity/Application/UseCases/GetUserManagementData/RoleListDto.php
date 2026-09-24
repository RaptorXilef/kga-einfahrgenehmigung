<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\GetUserManagementData;

/**
 * 100% logikfreies View-DTO für einen Listeneintrag im Tab "Rollen & Rechte".
 */
final readonly class RoleListDto
{
    public function __construct(
        public string $id,
        public string $idHash,
        public string $name,
        public string $iconUrl,
        public bool $canBeDeleted,
        public string $masterCheckboxAttr,
        public string $treeWrapperClass,
        public string $treeHtml,
    ) {
    }
}
