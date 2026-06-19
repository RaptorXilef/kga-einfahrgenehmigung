<?php

declare(strict_types=1);

namespace App\Contracts\Application;

/**
 * Interface für alle ausführbaren Action-Klassen (Single Action Controller).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
interface ActionInterface
{
    /**
     * Führt die definierte Aktion aus.
     *
     * @param  array<string, mixed> $post Formulardaten aus dem Request.
     * @return mixed                Statusmeldung oder Ergebnis der Ausführung.
     */
    public function execute(array $post): mixed;
}
