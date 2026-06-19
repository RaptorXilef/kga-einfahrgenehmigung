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
        public array $agreements,
        public string $datumBis,
        public string $datumVon,
        public string $email,
        public string $firma,
        public string $kennzeichen,
        public string $name,
        public string $parzelle,
        public string $templateKey,
        public string $typ,
        public string $voucher,
        public string $zweck,
    ) {
    }

    public static function fromArray(array $post): self
    {
        // 1. Array komplett säubern (XSS-Schutz, Trimmen)
        $sanitized = \array_map(function ($value): mixed {
            return \is_string($value) ? \trim(\strip_tags($value)) : $value;
        }, $post);

        $name     = $sanitized['name'] ?? '';
        $email    = $sanitized['email'] ?? '';
        $parzelle = $sanitized['parzelle'] ?? '';

        // 2. Strenge Validierung
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
            agreements: $sanitized['agreements'] ?? [],
            datumBis: $sanitized['datum_bis'] ?? '',
            datumVon: $sanitized['datum_von'] ?? '',
            email: $email,
            firma: $sanitized['firma'] ?? '',
            kennzeichen: $sanitized['kennzeichen'] ?? '',
            name: $name,
            parzelle: $parzelle,
            templateKey: $sanitized['template_key'] ?? '',
            typ: $sanitized['typ'] ?? 'pkw',
            voucher: $sanitized['voucher'] ?? '',
            zweck: $sanitized['zweck'] ?? '',
        );
    }

    /**
     * Baut ein absolut sicheres, typsicheres Array für den Service zusammen.
     * Das Leck ist geschlossen!
     */
    public function toArray(): array
    {
        return [
            'agreements'   => $this->agreements,
            'datum_bis'    => $this->datumBis,
            'datum_von'    => $this->datumVon,
            'email'        => $this->email,
            'firma'        => $this->firma,
            'kennzeichen'  => $this->kennzeichen,
            'name'         => $this->name,
            'parzelle'     => $this->parzelle,
            'template_key' => $this->templateKey,
            'typ'          => $this->typ,
            'voucher'      => $this->voucher,
            'zweck'        => $this->zweck,
        ];
    }
}
