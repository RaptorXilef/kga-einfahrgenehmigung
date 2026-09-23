<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\VerifyMagicLink;

use App\Application\Exception\ValidationException;

final readonly class HistoryVerifyTokenRequest
{
    private function __construct(public string $token)
    {
    }

    public static function fromArray(array $get): self
    {
        $token = \trim((string) ($get['token'] ?? ''));
        if ($token === '') {
            throw ValidationException::withMessage('Fehler: Sicherheits-Token fehlt.');
        }

        return new self($token);
    }
}
