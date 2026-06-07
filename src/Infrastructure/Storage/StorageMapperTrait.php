<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Core\Entity\Owner;
use App\Core\Entity\Permit;
use App\Core\Entity\Status;
use App\Core\Entity\Validity;
use App\Core\Entity\Vehicle;

/**
 * Trait für die bidirektionale Transformation zwischen Objekten und relationalen Arrays.
 *
 * Kapselt Konvertierungslogiken, um geschachtelte Domain-Entitäten (Permit, Owner, Vehicle...)
 * in flache, speicherbare Array-Strukturen zu transformieren und umgekehrt (Hydrierung).
 * Dient als Data Mapper für alle Storage-Engines.
 *
 * Path: src/Infrastructure/Storage/StorageMapperTrait.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
trait StorageMapperTrait
{
    /**
     * Wandelt eine Permit-Entität in ein flaches Array um.
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
            'code'               => $permit->code,
            'template_key'       => $permit->template_key,
            'name'               => $permit->owner->name,
            'email'              => $permit->owner->email,
            'parzelle'           => $permit->owner->parzelle,
            'typ'                => $permit->vehicle->typ,
            'kennzeichen'        => $permit->vehicle->kennzeichen,
            'firma'              => $permit->vehicle->firma,
            'von'                => $permit->validity->von->format('Y-m-d'),
            'bis'                => $permit->validity->bis->format('Y-m-d'),
            'preis'              => $permit->validity->preis,     // Harmonisierter Key
            'zweck'              => $permit->validity->zweck,
            'status'             => $permit->status->current,
            'is_suspended'       => (int) $permit->status->is_suspended,   // Harmonisierter Key
            'suspension_reason'  => $permit->status->suspension_reason,    // Harmonisierter Key
            'erstellt'           => $permit->erstellt->format('Y-m-d H:i:s'),
            'interner_kommentar' => $permit->interner_kommentar,           // Harmonisierter Key
            'agreements'         => \is_array($permit->agreements) ? \json_encode(
                $permit->agreements,
                \JSON_UNESCAPED_UNICODE,
            ) : '{}',
        ];
    }

    /**
     * Baut aus einem flachen Array eine Permit-Entität mit Value Objects.
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
        $tKey = $item['template_key'] ?? ($item['template_key'] ?? 'std_7');

        // Wir suchen flexibel nach alten und neuen Keys
        $name    = (string) ($item['name'] ?? ($item['pächter'] ?? 'Unbekannt'));
        $von     = (string) ($item['von'] ?? ($item['datum_von'] ?? 'now'));
        $bis     = (string) ($item['bis'] ?? ($item['datum_bis'] ?? 'now'));
        $created = (string) ($item['erstellt'] ?? ($item['erstellt_am'] ?? 'now'));

        // Die harmonisierten Keys (mit Fallback auf die alten camelCase Bezeichner deiner alten JSONs)
        $preis        = (float) ($item['preis'] ?? ($item['preis'] ?? 0.0));
        $is_suspended = (bool) ($item['is_suspended'] ?? ($item['is_suspended'] ?? false));
        $suspReason   = $item['suspension_reason'] ?? ($item['suspension_reason'] ?? null);
        $kommentar    = $item['interner_kommentar'] ?? ($item['interner_kommentar'] ?? null);

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

        // JSON-String wieder in ein Array umwandeln
        $agreements = $item['agreements'] ?? [];
        if (\is_string($agreements)) {
            $agreements = \json_decode($agreements, true) ?? [];
        }

        // 3. Entität hydrieren
        return new Permit(
            code: (string) ($item['code'] ?? ''),
            template_key: (string) $tKey,
            owner: new Owner(
                $name,
                (string) ($item['email'] ?? ''),
                \str_pad((string) ($item['parzelle'] ?? '0'), 4, '0', \STR_PAD_LEFT),
            ),
            vehicle: new Vehicle(
                (string) ($item['typ'] ?? 'pkw'),
                (string) ($item['kennzeichen'] ?? ''),
                $item['firma'] ?? null,
            ),
            validity: new Validity(
                $dtVon,
                $dtBis,
                $preis,
                (string) ($item['zweck'] ?? 'Privat'),
            ),
            status: new Status(
                (string) ($item['status'] ?? 'offen'),
                $is_suspended,
                $suspReason,
            ),
            erstellt: $dtCreated,
            interner_kommentar: $kommentar,
            agreements: $agreements,
        );
    }
}
