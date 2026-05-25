<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Storage\StorageInterface;
use App\Core\Entity\Permit;

/**
 * MySQL-Implementierung des Storage-Interfaces.
 *
 * Persistenz-Engine für relationale SQL-Datenbanken (MySQL / MariaDB).
 * Nutzt vorbereitete Statements (Prepared Statements) mit benannten Parametern zum Schutz
 * vor SQL-Injections und implementiert performante, datenbankseitige String-Säuberungen bei Suchen.
 * Kontext: Enterprise-Datenhaltungs-Backend für performante Großbetriebe.
 *
 * Path: src/Infrastructure/Storage/MySqlStorage.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class MySqlStorage implements StorageInterface
{
    use StorageMapperTrait;

    public function __construct(private \PDO $pdo)
    {
    }

    /**
     * Speichert oder aktualisiert eine Genehmigung via SQL-`REPLACE INTO` Statement.
     * Flacht die Objektstrukturen über das integrierte Trait ab.
     *
     * @param Permit $permit Das zu persistierende Genehmigungs-Objekt.
     *
     * @return bool True bei fehlerfreier SQL-Ausführung.
     */
    public function save(Permit $permit): bool
    {
        $sql = 'INSERT INTO permits (
            code, templateKey, name, email, kennzeichen, parzelle, typ,
            firma, zweck, preisSnapshot, von, bis, status, isSuspended,
            suspensionReason, erstellt, internerKommentar
        ) VALUES (
            :code, :templateKey, :name, :email, :kennzeichen, :parzelle, :typ,
            :firma, :zweck, :preisSnapshot, :von, :bis, :status, :isSuspended,
            :suspensionReason, :erstellt, :internerKommentar
        ) ON DUPLICATE KEY UPDATE
            templateKey = VALUES(templateKey),
            name = VALUES(name),
            email = VALUES(email),
            kennzeichen = VALUES(kennzeichen),
            parzelle = VALUES(parzelle),
            typ = VALUES(typ),
            firma = VALUES(firma),
            zweck = VALUES(zweck),
            preisSnapshot = VALUES(preisSnapshot),
            von = VALUES(von),
            bis = VALUES(bis),
            status = VALUES(status),
            isSuspended = VALUES(isSuspended),
            suspensionReason = VALUES(suspensionReason),
            internerKommentar = VALUES(internerKommentar);';
        // 'erstellt' wird beim Update weggelassen, da sich das Erstelldatum nicht ändern soll!

        return $this->pdo->prepare($sql)->execute($this->flattenEntity($permit));
    }

    /**
     * Holt eine Genehmigung über eine direkte Primärschlüsselabfrage (`code`) aus der DB.
     *
     * @param string $hash Der eindeutige Code.
     *
     * @return Permit|null Die hydrierte Entität oder null.
     */
    public function findByHash(string $hash): ?Permit
    {
        $hash = \strtoupper(\trim($hash));

        // 1. Direkter Match
        $stmt = $this->pdo->prepare('SELECT * FROM permits WHERE code = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return $this->mapToEntity($row);
        }

        // 2. Fallback: Suche nach der extrahierten ID am Ende des Codes
        $searchParts = \explode('-', $hash);
        $searchId    = \end($searchParts);

        // Sucht nach %-ID (langer Code) ODER genau der ID (kurzer Code)
        $stmt = $this->pdo->prepare('SELECT * FROM permits WHERE code LIKE ? OR code = ?');
        $stmt->execute(['%-' . $searchId, $searchId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Findet eine Genehmigung über das amtliche Kennzeichen direkt auf DB-Ebene.
     * Nutzt geschachtelte SQL-`REPLACE` Aufrufe zur Entfernung von Leerzeichen und Bindestrichen im Index
     * und sortiert Treffer-Kandidaten im PHP-Scope nach Gültigkeits-Relevanz.
     *
     * @param string $plate Das Such-Kennzeichen.
     *
     * @return Permit|null Die am besten passende Genehmigung oder null.
     */
    public function findByLicensePlate(string $plate): ?Permit
    {
        $searchPlate = \preg_replace('/[^A-Z0-9]/', '', \strtoupper($plate));

        if ($searchPlate === '') {
            return null;
        }

        // Wir nutzen SQL REPLACE, um Leerzeichen und Bindestriche in der DB beim Vergleich zu ignorieren
        $stmt = $this->pdo->prepare("
            SELECT * FROM permits
            WHERE REPLACE(REPLACE(kennzeichen, ' ', ''), '-', '') = ?
        ");
        $stmt->execute([$searchPlate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (! $rows) {
            return null;
        }

        // Mapping der Datenbankzeilen auf Entities
        $candidates = \array_map($this->mapToEntity(...), $rows);

        // Sortierung wie in JsonStorage:
        // 1. Aktive Genehmigungen zuerst
        // 2. Dann nach dem Enddatum (neueste zuerst)
        \usort($candidates, function (Permit $a, Permit $b) {
            $aValid = $a->isValid();
            $bValid = $b->isValid();

            if ($aValid && ! $bValid) {
                return -1;
            }
            if (! $aValid && $bValid) {
                return 1;
            }

            return $b->validity->bis <=> $a->validity->bis;
        });

        return $candidates[0];
    }

    /**
     * Ruft alle in der Tabelle `permits` hinterlegten Zeilen ab.
     *
     * @return array<int, Permit> Liste aller hydrierten Genehmigungs-Objekte.
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM permits');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return \array_map($this->mapToEntity(...), $rows);
    }

    /**
     * Migriert alle Datenbank-Datensätze in eine alternative Speicher-Engine.
     *
     * @param StorageInterface $target Das Ziel-Repository (z.B. JsonStorage).
     *
     * @return int Anzahl transferierter Datensätze.
     */
    public function migrateTo(StorageInterface $target): int
    {
        $count = 0;
        foreach ($this->getAll() as $permit) {
            if (! $target->save($permit)) {
                continue;
            }

            ++$count;
        }

        return $count;
    }
}
