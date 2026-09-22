<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageMails;

use App\Application\Exception\ValidationException;

final readonly class ResendMailRequest
{
    private function __construct(
        public string $timestamp,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $ts = \trim((string) ($post['timestamp'] ?? ''));

        if ($ts === '' || !\preg_match('/^[a-zA-Z0-9_\-\s:]+$/', $ts)) {
            throw ValidationException::withMessage('Fehler: Ungültiger oder fehlender Parameter (timestamp).');
        }

        return new self($ts);
    }
}
