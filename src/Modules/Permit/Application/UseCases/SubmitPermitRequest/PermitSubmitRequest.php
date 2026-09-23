<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\UseCases\SubmitPermitRequest;

use App\Application\Exception\ValidationException;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use DateTimeImmutable;
use Exception;

/**
 * DTO für das öffentliche Antragsformular (VSA Slice).
 * Säubert alle Eingaben (XSS-Schutz) und validiert Pflichtfelder.
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
        $sanitized = \array_map(fn ($value): mixed => \is_string($value) ? \trim(\strip_tags($value)) : $value, $post);

        $name = $sanitized['name'] ?? '';
        $email = $sanitized['email'] ?? '';
        $parzelle = $sanitized['parzelle'] ?? '';
        $kennzeichen = $sanitized['kennzeichen'] ?? '';
        $datumVon = $sanitized['datum_von'] ?? '';
        $datumBis = $sanitized['datum_bis'] ?? '';

        // 2. Strenge Validierung
        if ($name === '') {
            throw ValidationException::withMessage('Bitte geben Sie einen Namen ein.');
        }

        // Bot-Abwehr: Erzwinge Vor- und Nachname (mindestens ein Leerzeichen)
        if (!\str_contains($name, ' ')) {
            throw ValidationException::withMessage('Bitte geben Sie Ihren vollständigen Namen ein (Vor- und Nachname durch ein Leerzeichen getrennt).');
        }

        if ($email !== '' && !\filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessage('Die eingegebene E-Mail-Adresse ist ungültig.');
        }
        if ($parzelle === '') {
            throw ValidationException::withMessage('Bitte geben Sie eine Parzelle an.');
        }
        if ($datumVon === '' || $datumBis === '') {
            throw ValidationException::withMessage('Bitte geben Sie einen Gültigkeitszeitraum an.');
        }

        // Serverseitige Datums-Prüfung gegen Manipulation durch Bots
        try {
            $dtVon = new DateTimeImmutable($datumVon);
            $dtBis = new DateTimeImmutable($datumBis);
            $today = new DateTimeImmutable('today');

            // Setzen der Uhrzeit auf 00:00:00 für sauberen Tagesvergleich
            if ($dtVon->setTime(0, 0, 0) < $today) {
                throw ValidationException::withMessage('Das Startdatum darf nicht in der Vergangenheit liegen.');
            }
            if ($dtBis->setTime(0, 0, 0) < $dtVon->setTime(0, 0, 0)) {
                throw ValidationException::withMessage('Das Enddatum darf nicht vor dem Startdatum liegen.');
            }
        } catch (Exception $e) {
            // Reiche unsere eigenen Validierungs-Fehler sauber weiter
            if ($e instanceof ValidationException) {
                throw $e;
            }

            // Fange generische PHP-Exceptions (z.B. bei ungültigen Datumstexten wie "Apfel") ab
            throw ValidationException::withMessage('Das eingegebene Datumsformat ist ungültig.');
        }

        // Wir jagen Parzelle und Kennzeichen sofort durch die Value Objects (Shared Kernel)!
        // Schlägt die Format-Prüfung fehl, knallt es hier (wird von der Action abgefangen).
        new PlotNumber($parzelle);

        if ($kennzeichen !== '') {
            new LicensePlate($kennzeichen);
        }

        return new self(
            agreements: $sanitized['agreements'] ?? [],
            datumBis: $datumBis,
            datumVon: $datumVon,
            email: $email,
            firma: $sanitized['firma'] ?? '',
            kennzeichen: $kennzeichen,
            name: $name,
            parzelle: $parzelle,
            templateKey: $sanitized['template_key'] ?? '',
            typ: $sanitized['typ'] ?? 'pkw',
            voucher: $sanitized['voucher'] ?? '',
            zweck: $sanitized['zweck'] ?? '',
        );
    }
}
