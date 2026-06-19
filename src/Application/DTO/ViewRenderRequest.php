<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * DTO für alle allgemeinen View-Render-Requests (GET-Parameter).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ViewRenderRequest
{
    private function __construct(
        public string $message,
        public bool $isSuccess,
        public int $step,
        public int $loadArchive,
    ) {
    }

    public static function fromArray(array $get): self
    {
        return new self(
            \trim((string) ($get['msg'] ?? '')),
            isset($get['sent']),
            ($get['sent'] ?? '0') === '1' ? 2 : 1,
            (int) ($get['load_archive'] ?? 0),
        );
    }
}
