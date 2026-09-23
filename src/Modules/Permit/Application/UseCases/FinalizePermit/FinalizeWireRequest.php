<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\FinalizePermit;

use App\Application\Exception\ValidationException;

final readonly class FinalizeWireRequest
{
    private function __construct(
        public string $token,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $token = \trim((string) ($post['token'] ?? ''));

        if ($token === '' || !\preg_match('/^[a-zA-Z0-9_\-]+$/', $token)) {
            throw ValidationException::withMessage('Fehler: Ungültiger oder fehlender Checkout-Token.');
        }

        return new self($token);
    }
}
