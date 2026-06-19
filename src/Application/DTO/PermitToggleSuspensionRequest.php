<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für das Sperren/Entsperren einer Genehmigung.
 * Kapselt den Code, die gewählte Aktion und den optionalen Begründungstext.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitToggleSuspensionRequest
{
    private function __construct(
        public string $code,
        public bool $isSuspended,
        public string $reason,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        $code = \trim((string) ($post['code'] ?? ''));

        if ($code === '') {
            throw ValidationException::withMessage('Fehler: Fehlender Parameter (code).');
        }

        $isSuspended = ($post['action'] ?? '') === 'suspend_permit';
        $reason      = \trim(\strip_tags((string) ($post['reason'] ?? '')));

        return new self($code, $isSuspended, $reason);
    }
}
