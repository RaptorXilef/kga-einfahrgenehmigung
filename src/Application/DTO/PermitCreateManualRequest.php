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

    public static function fromArray(array $post): self
    {
        $sanitized = \array_map(fn ($value): mixed => \is_string($value) ? \trim(\strip_tags($value)) : $value, $post);

        $name = $sanitized['name'] ?? '';
        $parzelle = $sanitized['parzelle'] ?? '';
        $preis = (float) ($sanitized['preis'] ?? 0.0);

        if ($name === '') {
            throw ValidationException::withMessage('Fehler: Der Name darf nicht leer sein.');
        }
        if (!\str_contains($name, ' ')) {
            throw ValidationException::withMessage('Fehler: Bitte geben Sie Vor- und Nachname ein.');
        }
        if ($parzelle === '') {
            throw ValidationException::withMessage('Fehler: Die Parzelle darf nicht leer sein.');
        }
        if ($preis < 0) {
            throw ValidationException::withMessage('Fehler: Der Preis darf nicht negativ sein.');
        }

        $sanitized['manual_price'] = $preis;

        // Dynamisch vom UI: Checkbox "Sofort als Bezahlt markieren" prüfen
        $isPaid = isset($post['mark_as_paid']);
        $sanitized['status'] = $isPaid ? 'bezahlt' : 'offen';

        return new self(PermitFormData::fromArray($sanitized), isset($post['send_email']));
    }
}
