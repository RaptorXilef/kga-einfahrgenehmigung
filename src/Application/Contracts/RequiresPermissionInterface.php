<?php

declare(strict_types=1);

namespace App\Application\Contracts;

/**
 * Kennzeichnet Action-Klassen, deren Ausführung eine spezifische RBAC-Berechtigung erfordert.
 * Wird vom FrontendController vor dem Aufruf von execute() automatisch geprüft.
 */
interface RequiresPermissionInterface
{
    /**
     * Gibt den Berechtigungs-Schlüssel zurück, der für die Ausführung dieser Action benötigt wird.
     * Beispiel: 'vouchers.delete'
     */
    public function getRequiredPermission(): string;
}
