<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

use DateTimeImmutable;

interface PermitRepositoryInterface
{
    public function save(Permit $permit): void;

    public function findByCode(string $code): ?Permit;

    public function delete(string $code): void;

    /**
     * Prüft extrem performant direkt in der Datenbank, ob eine zeitliche Kollision vorliegt.
     */
    public function hasCollision(int $plotNumber, DateTimeImmutable $start, DateTimeImmutable $end): bool;

    /**
     * Prüft über alle Tabellen (Aktiv, Archiv, Storniert), ob ein Code bereits vergeben ist.
     */
    public function isCodeUnique(string $code): bool;
}
