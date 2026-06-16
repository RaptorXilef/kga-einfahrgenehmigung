<?php

declare(strict_types=1);

namespace App\Contracts\Application;

/**
 * Interface für alle ausführbaren Action-Klassen (Single Action Controller).
 *
 * Path: src/Contracts/Application/ActionInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface ActionInterface
{
    /**
     * Führt die definierte Aktion aus.
     *
     * @param  array<string, mixed> $post Formulardaten aus dem Request.
     * @return string               Statusmeldung oder Ergebnis der Ausführung.
     */
    public function execute(array $post): string;
}
