<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

use DateTimeImmutable;

interface PermitRepositoryInterface
{
    public function save(Permit $permit): void;

    public function findByCode(string $code): ?Permit;

    public function findByLicensePlate(string $plate): ?Permit;

    public function delete(string $code): void;

    public function deleteMultiple(array $codes): int;

    /**
     * @return Permit[] Liste aller Genehmigungen, bei denen eine E-Mail-Adresse hinterlegt ist.
     */
    public function findAllWithEmail(): array;

    /**
     * @return Permit[] Liste aller abgelaufenen Genehmigungen, die bezahlt oder storniert sind.
     */
    public function findExpired(DateTimeImmutable $cutoffDate): array;

    /**
     * @return Permit[] Liste aller noch nicht bezahlten, aktiven Genehmigungen.
     */
    public function findUnpaid(): array;

    /**
     * Prüft extrem performant direkt in der Datenbank, ob eine zeitliche Kollision vorliegt.
     */
    public function hasCollision(int $plotNumber, DateTimeImmutable $start, DateTimeImmutable $end): bool;

    /**
     * Prüft über alle Tabellen (Aktiv, Archiv, Storniert), ob ein Code bereits vergeben ist.
     */
    public function isCodeUnique(string $code): bool;
}
