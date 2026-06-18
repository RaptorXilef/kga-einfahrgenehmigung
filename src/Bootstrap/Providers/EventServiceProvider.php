<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Bootstrap\Container;
use App\Contracts\Event\EventDispatcherInterface;
use App\Infrastructure\Event\EventDispatcher;

/**
 * Registriert den Event Dispatcher und verknüpft Events mit ihren Listenern.
 *
 * Path: src/Bootstrap/Providers/EventServiceProvider.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final class EventServiceProvider
{
    public function register(Container $container): void
    {
        // 1. Den Dispatcher als Singleton im Container registrieren
        $container->bind(EventDispatcherInterface::class, function () {
            return new EventDispatcher();
        });

        // 2. Hier werden wir im nächsten Schritt unsere Events mit den Listenern verknüpfen!
        // Beispiel (noch auskommentiert):
        /*
        $dispatcher = $container->get(EventDispatcherInterface::class);
        $dispatcher->addListener(
            \App\Core\Event\PermitCreatedEvent::class,
            function($event) use ($container) {
                $container->get(\App\Application\Listener\SendPermitMailListener::class)->handle($event);
            }
        );
        */
    }
}
