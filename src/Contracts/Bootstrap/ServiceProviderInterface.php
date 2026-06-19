<?php

declare(strict_types=1);

namespace App\Contracts\Bootstrap;

use App\Bootstrap\Container;

/**
 * Interface für alle Service Provider im Dependency Injection Container.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
