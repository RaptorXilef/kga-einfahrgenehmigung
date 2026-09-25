<?php

declare(strict_types=1);

namespace App\Modules\Permit\Domain;

/**
 * Vertrag für das Speichern stornierter und anonymisierter Genehmigungen.
 */
interface CancelledPermitRepositoryInterface
{
    public function saveCancelled(Permit $permit): void;
}
