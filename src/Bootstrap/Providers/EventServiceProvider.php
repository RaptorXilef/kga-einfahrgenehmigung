<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Application\Listener\SendMagicLinkMailListener;
use App\Application\Listener\SendPermitMailListener;
use App\Application\Listener\SendVerificationMailListener;
use App\Bootstrap\Container;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Event\MagicLinkRequestedEvent;
use App\Core\Event\PermitCreatedEvent;
use App\Core\Event\VerificationRequestedEvent;
use App\Core\Service\BankQrGenerator;
use App\Core\Service\HolidayService;
use App\Infrastructure\Event\EventDispatcher;

/**
 * Zentraler Event-Verteiler-Provider. Verknüpft alle Domain-Events mit ihren Listenern.
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

        // 2. Alle Listener im Container registrieren
        $container->bind(SendPermitMailListener::class, function () use ($container) {
            return new SendPermitMailListener(
                $container->get(BankQrGenerator::class),
                $container->get(ConfigInterface::class),
                $container->get(HolidayService::class),
                $container->get(MailServiceInterface::class),
            );
        });

        $container->bind(SendVerificationMailListener::class, function () use ($container) {
            return new SendVerificationMailListener(
                $container->get(ConfigInterface::class),
                $container->get(MailServiceInterface::class),
            );
        });

        $container->bind(SendMagicLinkMailListener::class, function () use ($container) {
            return new SendMagicLinkMailListener(
                $container->get(ConfigInterface::class),
                $container->get(MailServiceInterface::class),
            );
        });

        // 3. Events an die Listener mappen
        $dispatcher = $container->get(EventDispatcherInterface::class);

        $dispatcher->addListener(PermitCreatedEvent::class, function ($event) use ($container): void {
            $container->get(SendPermitMailListener::class)->handle($event);
        });

        $dispatcher->addListener(VerificationRequestedEvent::class, function ($event) use ($container): void {
            $container->get(SendVerificationMailListener::class)->handle($event);
        });

        $dispatcher->addListener(MagicLinkRequestedEvent::class, function ($event) use ($container): void {
            $container->get(SendMagicLinkMailListener::class)->handle($event);
        });
    }
}
