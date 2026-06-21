<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Bootstrap\Container;
use App\Contracts\Bootstrap\ServiceProviderInterface;

/**
 * Da der Container Autowiring unterstützt, müssen reine Domänen-Services
 * hier nicht mehr händisch gebunden werden.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // Alle Services (PermitService, AuthService, ExportService etc.)
        // werden vom Container on-the-fly via Reflection instanziiert.
    }
}
