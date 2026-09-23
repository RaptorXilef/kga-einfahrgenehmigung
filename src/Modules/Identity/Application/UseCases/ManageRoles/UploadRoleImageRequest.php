<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageRoles;

use App\Application\Exception\ValidationException;

final readonly class UploadRoleImageRequest
{
    private function __construct(
        public string $roleId,
        public array $file,
    ) {
    }

    public static function fromRequest(array $post, array $files): self
    {
        $roleId = \trim((string) ($post['group_id'] ?? ''));

        if ($roleId === '') {
            throw ValidationException::withMessage('Fehler: Fehlende Rollen-ID.');
        }

        $file = $files['avatar'] ?? null;
        if (!$file || !isset($file['error']) || $file['error'] !== 0) {
            throw ValidationException::withMessage('Fehler: Ungültiger oder fehlender Datei-Upload.');
        }

        return new self($roleId, $file);
    }
}
