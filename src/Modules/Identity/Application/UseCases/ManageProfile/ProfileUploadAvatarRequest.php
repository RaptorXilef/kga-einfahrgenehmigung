<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\ManageProfile;

use App\Application\Exception\ValidationException;

final readonly class ProfileUploadAvatarRequest
{
    private function __construct(public array $file)
    {
    }

    public static function fromFiles(array $files): self
    {
        $file = $files['avatar'] ?? null;
        if (!$file || !isset($file['error']) || $file['error'] !== 0) {
            throw ValidationException::withMessage('Fehler: Es wurde keine gültige Bilddatei hochgeladen.');
        }

        return new self($file);
    }
}
