<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Bootstrap\Container;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Service\BankQrGenerator;
use App\Core\Service\HolidayService;
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
        // 1. Dispatcher registrieren
        $container->bind(EventDispatcherInterface::class, function () {
            return new EventDispatcher();
        });

        // 2. Den Listener registrieren
        $container->bind(\App\Application\Listener\SendPermitMailListener::class, function () use ($container) {
            return new \App\Application\Listener\SendPermitMailListener(
                $container->get(BankQrGenerator::class),
                $container->get(ConfigInterface::class),
                $container->get(HolidayService::class),
                $container->get(MailServiceInterface::class),
            );
        });

        // 3. Dem Dispatcher sagen: "Wenn ein PermitCreatedEvent fliegt, rufe den Listener auf!"
        $dispatcher = $container->get(EventDispatcherInterface::class);
        $dispatcher->addListener(
            \App\Core\Event\PermitCreatedEvent::class,
            function ($event) use ($container): void {
                $container->get(\App\Application\Listener\SendPermitMailListener::class)->handle($event);
            },
        );
    }
}
