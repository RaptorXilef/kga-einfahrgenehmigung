<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Contracts\Bootstrap\ServiceProviderInterface;
use App\Contracts\DependencyInjection\ContainerInterface;

/**
 * Registriert nur noch Controller/Actions, die NICHT automatisch via
 * Reflection (Autowiring) aufgelöst werden können.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class ControllerServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        // Da fast alle Controller und Actions sauberes Type-Hinting nutzen,
        // erledigt der Container den Rest magisch von selbst!
        // Diese Datei ist nun wunderbar leer und bereit für Edge-Cases.
    }
}
