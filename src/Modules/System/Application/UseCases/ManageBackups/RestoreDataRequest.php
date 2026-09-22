<?php

declare(strict_types=1);

namespace App\Modules\System\Application\UseCases\ManageBackups;

use App\Application\Exception\ValidationException;

final readonly class RestoreDataRequest
{
    private function __construct(
        public string $filename,
        public string $target,
        public int $mode,
    ) {
    }

    public static function fromArray(array $post): self
    {
        $filename = \trim((string) ($post['filename'] ?? ''));
        $target = \trim((string) ($post['target'] ?? ''));
        $mode = (int) ($post['mode'] ?? 1);

        if ($filename === '' || $target === '' | !\in_array($mode, [1, 2, 3], true)) {
            throw ValidationException::withMessage('Ungültige Wiederherstellungs-Parameter.');
        }

        return new self($filename, $target, $mode);
    }
}
