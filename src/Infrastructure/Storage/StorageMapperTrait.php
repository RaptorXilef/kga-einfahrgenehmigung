<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Path: src/Infrastructure/Storage/StorageMapperTrait.php
 */

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Core\Entity\Owner;
use App\Core\Entity\Permit;
use App\Core\Entity\Status;
use App\Core\Entity\Validity;
use App\Core\Entity\Vehicle;

trait StorageMapperTrait
{
    /**
     * Wandelt eine Permit-Entität in ein flaches Array um.
     *
     * @return array<string, mixed>
     */
    private function flattenEntity(Permit $permit): array
    {
        return [
            'code'              => $permit->code,
            'templateKey'       => $permit->templateKey,
            'name'              => $permit->owner->name,
            'email'             => $permit->owner->email,
            'parzelle'          => $permit->owner->parzelle,
            'typ'               => $permit->vehicle->typ,
            'kennzeichen'       => $permit->vehicle->kennzeichen,
            'firma'             => $permit->vehicle->firma,
            'von'               => $permit->validity->von->format('Y-m-d'),
            'bis'               => $permit->validity->bis->format('Y-m-d'),
            'preisSnapshot'     => $permit->validity->preisSnapshot,
            'zweck'             => $permit->validity->zweck,
            'status'            => $permit->status->current,
            'isSuspended'       => (int) $permit->status->isSuspended,
            'suspensionReason'  => $permit->status->suspensionReason,
            'erstellt'          => $permit->erstellt->format('Y-m-d H:i:s'),
            'internerKommentar' => $permit->internerKommentar,
        ];
    }

    /**
     * Baut aus einem flachen Array eine Permit-Entität mit Value Objects.
     *
     * @param array<string, mixed> $item
     */
    public function mapToEntity(array $item): Permit
    {
        return new Permit(
            code: (string) $item['code'],
            templateKey: (string) ($item['templateKey'] ?? 'std_7'),
            owner: new Owner(
                (string) $item['name'],
                (string) $item['email'],
                (string) $item['parzelle'],
            ),
            vehicle: new Vehicle(
                (string) ($item['typ'] ?? 'unknown'), // 'unknown' statt fest 'pkw'
                (string) $item['kennzeichen'],
                $item['firma'] ?? null,
            ),
            validity: new Validity(
                new \DateTimeImmutable((string) $item['von']),
                new \DateTimeImmutable((string) $item['bis']),
                (float) ($item['preisSnapshot'] ?? 0.0),
                (string) ($item['zweck'] ?? 'Privat'),
            ),
            status: new Status(
                (string) ($item['status'] ?? 'wartend'),
                (bool) ($item['isSuspended'] ?? false),
                $item['suspensionReason'] ?? null,
            ),
            erstellt: new \DateTimeImmutable((string) ($item['erstellt'] ?? 'now')),
            internerKommentar: $item['internerKommentar'] ?? null,
        );
    }
}
