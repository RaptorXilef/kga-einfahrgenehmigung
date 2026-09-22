<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageBackups;

use App\Application\Exception\ValidationException;

final readonly class TruncateTargetRequest
{
    private function __construct(
        public string $target,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $target = \trim((string) ($post['target'] ?? ''));
        if ($target === '') {
            throw ValidationException::withMessage('Fehler: Kein Zielbereich ausgewählt.');
        }

        return new self($target);
    }
}
