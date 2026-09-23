<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\CreateManualPermit;

use App\Application\Exception\ValidationException;

final readonly class PermitCreateManualRequest
{
    public function __construct(
        public string $name,
        public string $email,
        public string $parzelle,
        public string $typ,
        public string $kennzeichen,
        public string $firma,
        public string $zweck,
        public string $templateKey,
        public string $datumVon,
        public string $datumBis,
        public float $manualPrice,
        public string $status,
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

        $isPaid = isset($post['mark_as_paid']);

        return new self(
            name: $name,
            email: $sanitized['email'] ?? '',
            parzelle: $parzelle,
            typ: $sanitized['typ'] ?? 'pkw',
            kennzeichen: $sanitized['kennzeichen'] ?? '',
            firma: $sanitized['firma'] ?? '',
            zweck: $sanitized['zweck'] ?? 'Privat',
            templateKey: $sanitized['template_key'] ?? 'std_7',
            datumVon: $sanitized['datum_von'] ?? \date('Y-m-d'),
            datumBis: $sanitized['datum_bis'] ?? \date('Y-m-d'),
            manualPrice: $preis,
            status: $isPaid ? 'bezahlt' : 'offen',
            sendEmail: isset($post['send_email']),
        );
    }
}
