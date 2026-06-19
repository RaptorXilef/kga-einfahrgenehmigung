<?php

declare(strict_types=1);

namespace App\Core\DTO;

/**
 * TODO DOCBLOCK
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitFormData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $parzelle,
        public string $typ,
        public string $kennzeichen,
        public ?string $firma,
        public string $zweck,
        public string $templateKey,
        public string $datumVon,
        public string $datumBis,
        public float $manualPrice = 0.0,
        public string $status = 'offen',
        public ?string $internerKommentar = null,
        public array $agreements = [],
        public string $voucher = '',
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['name'] ?? '',
            $data['email'] ?? '',
            $data['parzelle'] ?? '',
            $data['typ'] ?? 'pkw',
            $data['kennzeichen'] ?? '',
            $data['firma'] ?? null,
            $data['zweck'] ?? 'Privat',
            $data['template_key'] ?? 'std_7',
            $data['datum_von'] ?? 'now',
            $data['datum_bis'] ?? 'now',
            (float) ($data['manual_price'] ?? ($data['preis'] ?? 0.0)),
            $data['status'] ?? 'offen',
            $data['interner_kommentar'] ?? null,
            $data['agreements'] ?? [],
            $data['voucher'] ?? '',
        );
    }
}
