<?php

declare(strict_types=1);

namespace App\Contracts\Application;

/**
 * Interface für alle HTTP-Middlewares (Türsteher).
 *
 * Path: src/Contracts/Application/MiddlewareInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface MiddlewareInterface
{
    /**
     * Verarbeitet den Request und entscheidet, ob er an die nächste Schicht weitergegeben wird.
     *
     * @param array<string, mixed> $requestData Die gesammelten Request-Daten (GET, POST, etc.).
     * @param callable             $next        Die nächste Middleware oder die finale Action.
     */
    public function process(array $requestData, callable $next): mixed;
}
