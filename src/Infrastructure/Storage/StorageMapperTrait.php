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
use App\Core\ValueObject\PlotNumber;

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
        // 1. Daten-Abgleich: Wir prüfen auf den neuen sauberen Key, falls nicht vorhanden, nutzen wir den Legacy-Key
        // Rückwärts-Mapping für JSON-Dateien, die noch "template_key" haben könnten
        $tKey = $item['template_key'] ?? 'std_7';

        // Wir suchen flexibel nach alten und neuen Keys
        $name    = (string) ($item['name'] ?? 'Unbekannt');
        $von     = (string) ($item['von'] ?? 'now');
        $bis     = (string) ($item['bis'] ?? 'now');
        $created = (string) ($item['erstellt'] ?? 'now');

        // Die harmonisierten Keys (mit Fallback auf die alten camelCase Bezeichner deiner alten JSONs)
        $preis        = (float) ($item['preis'] ?? 0.0);
        $is_suspended = (bool) ($item['is_suspended'] ?? false);
        $suspReason   = $item['suspension_reason'] ?? null;
        $kommentar    = $item['interner_kommentar'] ?? null;

        // 2. Datumsobjekte sicher generieren
        try {
            $dtVon = new \DateTimeImmutable($von);
        } catch (\Exception) {
            $dtVon = new \DateTimeImmutable('today'); // Ausfall-Sicherheit
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
        if (\is_string($agreements)) {
            $agreements = $this->jsonHelper->decode($agreements);
        }

        $statusEnum = PermitStatus::tryFrom((string) ($item['status'] ?? 'offen')) ?? PermitStatus::Offen;

        // 3. Entität hydrieren
        return new Permit(
            code: (string) ($item['code'] ?? ''),
            template_key: (string) $tKey,
            owner: new Owner(
                $name,
                new EmailAddress((string) ($item['email'] ?? '')),
                new PlotNumber((string) ($item['parzelle'] ?? '0')),
            ),
            vehicle: new Vehicle(
                (string) ($item['typ'] ?? 'pkw'),
                new LicensePlate((string) ($item['kennzeichen'] ?? '')),
                $item['firma'] ?? null,
            ),
            validity: new Validity(
                $dtVon,
                $dtBis,
                $preis,
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
            'bezahlt_am'         => $permit->bezahlt_am ? $permit->bezahlt_am->format('Y-m-d H:i:s') : null, // Serialisierung für JSON/SQL
            'bis'                => $permit->getValidUntil()->format('Y-m-d'),
            'code'               => $permit->code,
            'email'              => $permit->owner->email->value,
            'erstellt'           => $permit->getCreatedAt()->format('Y-m-d H:i:s'),
            'firma'              => $permit->getCompany(),
            'interner_kommentar' => $permit->interner_kommentar,
            'is_suspended'       => (int) $permit->isSuspended(),
            'kennzeichen'        => $permit->vehicle->kennzeichen->value,
            'name'               => $permit->getOwnerName(),
            'parzelle'           => $permit->owner->parzelle->value,
            'preis'              => $permit->getPrice(),
            'reminder_sent'      => (int) $permit->status->reminder_sent,
            'status'             => $permit->getStatus()->value,
            'suspension_reason'  => $permit->getSuspensionReason(),
            'template_key'       => $permit->template_key,
            'typ'                => $permit->vehicle->typ,
            'von'                => $permit->getValidFrom()->format('Y-m-d'),
            'zweck'              => $permit->getPurpose(),
        ];
    }
}
