<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für das öffentliche Antragsformular.
 * Säubert alle Eingaben (XSS-Schutz) und validiert Pflichtfelder.
 *
 * Path: src/Application/DTO/PermitSubmitRequest.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitSubmitRequest
{
    private function __construct(
        public string $name,
        public string $email,
        public string $parzelle,
        public string $kennzeichen,
        public string $typ,
        public string $firma,
        public string $zweck,
        public string $templateKey,
        public string $voucher,
        public string $datumVon,
        public string $datumBis,
        public array $agreements,
        public array $rawSanitized, // Hält das saubere Array für Session & Service
    ) {
    }

    // TODO DOCBLOCK
    public static function fromArray(array $post): self
    {
        // 1. Array komplett säubern (XSS-Schutz, Trimmen)
        $sanitized = \array_map(function ($value) {
            return \is_string($value) ? \trim(\strip_tags($value)) : $value;
        }, $post);

        $name     = $sanitized['name'] ?? '';
        $email    = $sanitized['email'] ?? '';
        $parzelle = $sanitized['parzelle'] ?? '';

        // 2. Strenge Validierung für das öffentliche Formular
        if ($name === '') {
            throw ValidationException::withMessage('Bitte geben Sie einen Namen ein.');
        }
        if ($email !== '' && ! \filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessage('Die eingegebene E-Mail-Adresse ist ungültig.');
        }
        if ($parzelle === '') {
            throw ValidationException::withMessage('Bitte geben Sie eine Parzelle an.');
        }

        return new self(
            $name,
            $email,
            $parzelle,
            $sanitized['kennzeichen'] ?? '',
            $sanitized['typ'] ?? 'pkw',
            $sanitized['firma'] ?? '',
            $sanitized['zweck'] ?? '',
            $sanitized['template_key'] ?? '',
            $sanitized['voucher'] ?? '',
            $sanitized['datum_von'] ?? '',
            $sanitized['datum_bis'] ?? '',
            $sanitized['agreements'] ?? [],
            $sanitized,
        );
    }

    /**
     * Gibt das komplett bereinigte Array für Legacy-Services zurück.
     */
    public function toArray(): array
    {
        return $this->rawSanitized;
    }
}
