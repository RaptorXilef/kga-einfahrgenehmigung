<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für das manuelle Anlegen einer Genehmigung im Admin-Panel.
 *
 * Path: src/Application/DTO/PermitCreateManualRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitCreateManualRequest
{
    private function __construct(
        public array $rawSanitized,
        public bool $sendEmail,
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        $sanitized = \array_map(function ($value): mixed {
            return \is_string($value) ? \trim(\strip_tags($value)) : $value;
        }, $post);

        $name     = $sanitized['name'] ?? '';
        $parzelle = $sanitized['parzelle'] ?? '';
        $preis    = (float) ($sanitized['preis'] ?? 0.0);

        if ($name === '') {
            throw ValidationException::withMessage('Fehler: Der Name darf nicht leer sein.');
        }
        if ($parzelle === '') {
            throw ValidationException::withMessage('Fehler: Die Parzelle darf nicht leer sein.');
        }
        if ($preis < 0) {
            throw ValidationException::withMessage('Fehler: Der Preis darf nicht negativ sein.');
        }

        // Sicherstellen, dass der Preis als korrekter String/Float im Array liegt
        $sanitized['preis'] = $preis;
        $sendEmail          = isset($post['send_email']);

        return new self($sanitized, $sendEmail);
    }
}
