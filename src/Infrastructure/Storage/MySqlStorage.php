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
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class MySqlStorage implements StorageInterface
{
    use StorageMapperTrait;

    public function __construct(private \PDO $pdo)
    {
    }

    // --- Public Write ---

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
        $sql = 'INSERT INTO `permits` (
                    code, template_key, name, email, kennzeichen, parzelle,
                    typ, firma, zweck, preis, von, bis, status, is_suspended,
                    suspension_reason, erstellt, interner_kommentar, agreements,
                    bezahlt_am
                ) VALUES (
                    :code, :template_key, :name, :email, :kennzeichen, :parzelle,
                    :typ, :firma, :zweck, :preis, :von, :bis, :status, :is_suspended,
                    :suspension_reason, :erstellt, :interner_kommentar, :agreements,
                    :bezahlt_am
                ) ON DUPLICATE KEY UPDATE
                    template_key = VALUES(template_key),
                    name = VALUES(name),
                    email = VALUES(email),
                    kennzeichen = VALUES(kennzeichen),
                    parzelle = VALUES(parzelle),
                    typ = VALUES(typ),
                    firma = VALUES(firma),
                    zweck = VALUES(zweck),
                    preis = VALUES(preis),
                    von = VALUES(von),
                    bis = VALUES(bis),
                    status = VALUES(status),
                    is_suspended = VALUES(is_suspended),
                    suspension_reason = VALUES(suspension_reason),
                    interner_kommentar = VALUES(interner_kommentar),
                    agreements = VALUES(agreements),
                    bezahlt_am = VALUES(bezahlt_am);';
        // 'erstellt' wird beim Update weggelassen, da sich das Erstelldatum nicht ändern soll!

        return $this->pdo->prepare($sql)->execute($this->flattenEntity($permit));
    }

    /**
     * Löscht eine Genehmigung unwiderruflich aus der MySQL-Datenbank.
     *
     * @param string $code Der eindeutige Hash/Code der Genehmigung.
     *
     * @return bool True, wenn der Datensatz erfolgreich gelöscht wurde.
     */
    public function delete(string $code): bool
    {
        // Nutze rowCount() statt dem reinen execute()-Ergebnis,
        // um den echten Lösch-Status (True/False) ans System zurückzugeben.
        $stmt = $this->pdo->prepare('DELETE FROM `permits` WHERE code = ?');
        $stmt->execute([$code]);

        return $stmt->rowCount() > 0;
    }

    // TODO DOCBLOCK
    public function deleteMultiple(array $codes): int
    {
        if (empty($codes)) {
            return 0;
        }

        $placeholders = \implode(',', \array_fill(0, \count($codes), '?'));
        $stmt         = $this->pdo->prepare("DELETE FROM `permits` WHERE code IN ($placeholders)");
        $stmt->execute(\array_values($codes));

        return $stmt->rowCount();
    }

    // --- Public Read ---

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
        $stmt = $this->pdo->prepare('SELECT * FROM `permits` WHERE code = ?');
        $stmt->execute([$hash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            return $this->mapToEntity($row);
        }

        // 2. Fallback: Suche nach der extrahierten ID am Ende des Codes
        $searchParts = \explode('-', $hash);
        $searchId    = \end($searchParts);

        // Sucht nach %-ID (langer Code) ODER genau der ID (kurzer Code)
        $stmt = $this->pdo->prepare('SELECT * FROM `permits` WHERE code LIKE ? OR code = ?');
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
            SELECT * FROM `permits`
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
        \usort($candidates, function (Permit $a, Permit $b): int {
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
        $stmt = $this->pdo->query('SELECT * FROM `permits`');
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return \array_map($this->mapToEntity(...), $rows);
    }

    // --- Public Migrations ---

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

    public function import(array $data): void
    {
        foreach ($data as $key => $item) {
            if (! isset($item['code'])) {
                $item['code'] = $key;
            }
            $this->save($this->mapToEntity($item));
        }
    }
}
