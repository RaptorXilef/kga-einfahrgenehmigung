<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;
use App\Core\DTO\PermitFormData;

/**
 * DTO für das manuelle Anlegen einer Genehmigung im Admin-Panel.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitCreateManualRequest
{
    public function __construct(
        public PermitFormData $formData,
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

        $sanitized['manual_price'] = $preis; // Wichtig für PermitService

        return new self(PermitFormData::fromArray($sanitized), isset($post['send_email']));
    }
}
