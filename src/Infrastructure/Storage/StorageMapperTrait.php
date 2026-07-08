<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Core\Entity\Owner;
use App\Core\Entity\Permit;
use App\Core\Entity\PermitStatus;
use App\Core\Entity\Status;
use App\Core\Entity\Validity;
use App\Core\Entity\Vehicle;
use App\Core\ValueObject\EmailAddress;
use App\Core\ValueObject\LicensePlate;
use App\Core\ValueObject\PermitCode;
use App\Core\ValueObject\PlotNumber;
use App\Core\ValueObject\Price;
use App\Core\ValueObject\TemplateKey;

/**
 * Trait für die bidirektionale Transformation zwischen Objekten und relationalen Arrays.
 *
 * Kapselt Konvertierungslogiken, um geschachtelte Domain-Entitäten (Permit, Owner, Vehicle...)
 * in flache, speicherbare Array-Strukturen zu transformieren und umgekehrt (Hydrierung).
 * Dient als Data Mapper für alle Storage-Engines.
 *
 * @property \App\Contracts\System\JsonHelperInterface $jsonHelper
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
trait StorageMapperTrait
{
    /**
     * Baut aus einem flachen Array eine Permit-Entität mit Value Objects.
     *
     * Public Data to Object
     *
     * Hydriert ein primitives, assoziatives Rohdaten-Array in ein stark typisiertes Permit-Objekt.
     * Unterstützt Legacy-Feldnamen (Abwärtskompatibilität für Altdaten wie 'pächter' oder 'erstellt_am'),
     * padded Parzellennummern auf 4 Stellen auf und baut rekursiv alle benötigten Unter-Werteobjekte auf.
     *
     * @param array<string, mixed> $item Zeilen-Rohdaten aus einer JSON-Datei oder SQL-Abfrage.
     *
     * @return Permit Die fertig zusammengesetzte, einsatzbereite Domain-Entität.
     */
    public function mapToEntity(array $item): Permit
    {
        // 1. Daten-Abgleich
        $tKeyStr = \trim((string) ($item['template_key'] ?? 'std_7'));
        if ($tKeyStr === '') {
            $tKeyStr = 'std_7';
        }

        $emailStr = \trim((string) ($item['email'] ?? ''));
        $emailObj = ($emailStr !== '' && $emailStr !== '0') ? new EmailAddress($emailStr) : null;

        $pzStr = \trim((string) ($item['parzelle'] ?? '0'));
        if ($pzStr === '') {
            $pzStr = '0000';
        }

        $kzStr = \trim((string) ($item['kennzeichen'] ?? ''));
        if ($kzStr === '') {
            $kzStr = 'XXX-XX 9999'; // Legacy Fallback
        }

        $codeStr = \trim((string) ($item['code'] ?? ''));
        if ($codeStr === '') {
            $codeStr = 'LEGACY-' . \uniqid();
        }

        $name    = (string) ($item['name'] ?? 'Unbekannt');
        $von     = (string) ($item['von'] ?? 'now');
        $bis     = (string) ($item['bis'] ?? 'now');
        $created = (string) ($item['erstellt'] ?? 'now');

        $is_suspended = (bool) ($item['is_suspended'] ?? false);
        $suspReason   = $item['suspension_reason'] ?? null;
        $kommentar    = $item['interner_kommentar'] ?? null;

        // 2. Datumsobjekte sicher generieren
        try {
            $dtVon = new \DateTimeImmutable($von);
        } catch (\Exception) {
            $dtVon = new \DateTimeImmutable('today');
        }

        try {
            $dtBis = new \DateTimeImmutable($bis);
        } catch (\Exception) {
            $dtBis = new \DateTimeImmutable('tomorrow');
        }

        try {
            $dtCreated = new \DateTimeImmutable($created);
        } catch (\Exception) {
            $dtCreated = new \DateTimeImmutable('now');
        }

        // Hydrierung des neuen bezahlt_am Feldes aus den Rohdaten (JSON oder SQL)
        $bezahltAmStr = $item['bezahlt_am'] ?? null;
        $dtBezahltAm  = null;
        if ($bezahltAmStr && $bezahltAmStr !== '0000-00-00 00:00:00' && $bezahltAmStr !== 'null') {
            try {
                $dtBezahltAm = new \DateTimeImmutable($bezahltAmStr);
            } catch (\Exception) {
            }
        }

        $agreements = $item['agreements'] ?? [];
        if (\is_string($agreements) && \property_exists($this, 'jsonHelper')) {
            $agreements = $this->jsonHelper->decode($agreements);
        }

        $statusEnum = PermitStatus::tryFrom((string) ($item['status'] ?? 'offen')) ?? PermitStatus::Offen;

        // 3. Entität hydrieren
        return new Permit(
            code: clone new PermitCode($codeStr),
            template_key: clone new TemplateKey($tKeyStr),
            owner: new Owner(
                $name,
                $emailObj,
                clone new PlotNumber($pzStr),
            ),
            vehicle: new Vehicle(
                (string) ($item['typ'] ?? 'pkw'),
                clone new LicensePlate($kzStr),
                $item['firma'] ?? null,
            ),
            validity: new Validity(
                $dtVon,
                $dtBis,
                new Price((float) ($item['preis'] ?? 0.0)),
                (string) ($item['zweck'] ?? 'Privat'),
            ),
            status: new Status(
                $statusEnum,
                $is_suspended,
                $suspReason,
                (bool) ($item['reminder_sent'] ?? false),
            ),
            erstellt: $dtCreated,
            interner_kommentar: $kommentar,
            agreements: $agreements,
            state: null,
            bezahlt_am: $dtBezahltAm,
        );
    }

    /**
     * Wandelt eine Permit-Entität in ein flaches Array um.
     *
     * Private Object to Data
     *
     * Transformiert eine hochkomplexe Permit-Entität in ein eindimensionales, primitives Datenarray.
     * Formatiert DateTime-Objekte in ISO-Strings für SQL- oder JSON-Schreibvorgänge.
     *
     * @param Permit $permit Die zu dekonstruierende Entität.
     *
     * @return array<string, mixed> Flaches Konvertierungs-Array für Treiber-Injektionen.
     */
    private function flattenEntity(Permit $permit): array
    {
        return [
            'agreements'         => \is_array($permit->agreements) ? \json_encode($permit->agreements, \JSON_UNESCAPED_UNICODE) : '{}',
            'bezahlt_am'         => $permit->bezahlt_am ? $permit->bezahlt_am->format('Y-m-d H:i:s') : null,
            'bis'                => $permit->getValidUntil()->format('Y-m-d'),
            'code'               => $permit->code->value,
            'email'              => $permit->owner->email ? $permit->owner->email->value : '',
            'erstellt'           => $permit->getCreatedAt()->format('Y-m-d H:i:s'),
            'firma'              => $permit->getCompany(),
            'interner_kommentar' => $permit->interner_kommentar,
            'is_suspended'       => (int) $permit->isSuspended(),
            'kennzeichen'        => $permit->vehicle->kennzeichen->value,
            'name'               => $permit->getOwnerName(),
            'parzelle'           => $permit->owner->parzelle->value,
            'preis'              => $permit->validity->preis->value,
            'reminder_sent'      => (int) $permit->status->reminder_sent,
            'status'             => $permit->getStatus()->value,
            'suspension_reason'  => $permit->getSuspensionReason(),
            'template_key'       => $permit->template_key->value,
            'typ'                => $permit->vehicle->typ,
            'von'                => $permit->getValidFrom()->format('Y-m-d'),
            'zweck'              => $permit->getPurpose(),
        ];
    }
}
