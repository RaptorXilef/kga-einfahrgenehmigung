<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;

final readonly class UploadUserAvatarRequest
{
    private function __construct(
        public string $userId,
        public array $file,
    ) {
    }

    public static function fromRequest(array $post, array $files): self
    {
        $userId = \trim((string) ($post['user_id'] ?? ''));

        if ($userId === '') {
            throw ValidationException::withMessage('Fehler: Fehlende Benutzer-ID.');
        }

        $file = $files['avatar'] ?? null;
        if (!$file || !isset($file['error']) || $file['error'] !== 0) {
            throw ValidationException::withMessage('Fehler: Ungültiger oder fehlender Datei-Upload.');
        }

        return new self($userId, $file);
    }
}
