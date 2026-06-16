<?php

declare(strict_types=1);

namespace App\Contracts\Bootstrap;

use App\Bootstrap\Container;

/**
 * Interface für alle Service Provider im Dependency Injection Container.
 *
 * Path: src/Contracts/Bootstrap/ServiceProviderInterface.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
interface ServiceProviderInterface
{
    /**
     * Registriert Services, Repositories oder Controller im Container.
     *
     * @param Container $container Die Instanz des DI-Containers.
     */
    public function register(Container $container): void;
}
