<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Application\Listener\DeleteGroupImageListener;
use App\Application\Listener\SendMagicLinkMailListener;
use App\Application\Listener\SendPermitCancelledMailListener;
use App\Application\Listener\SendPermitMailListener;
use App\Application\Listener\SendVerificationMailListener;
use App\Bootstrap\Container;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Event\GroupDeletedEvent;
use App\Core\Event\MagicLinkRequestedEvent;
use App\Core\Event\PermitCancelledEvent;
use App\Core\Event\PermitCreatedEvent;
use App\Core\Event\VerificationRequestedEvent;
use App\Core\Service\BankQrGenerator;
use App\Core\Service\HolidayService;
use App\Core\Service\PermitService;
use App\Infrastructure\Event\EventDispatcher;

/**
 * Zentraler Event-Verteiler-Provider. Verknüpft alle Domain-Events mit ihren Listenern.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
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
                $container->get(PermitService::class),
            );
        });

        $container->bind(SendPermitCancelledMailListener::class, function () use ($container) {
            return new SendPermitCancelledMailListener(
                $container->get(ConfigInterface::class),
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

        $container->bind(DeleteGroupImageListener::class, function () use ($container) {
            return new DeleteGroupImageListener(
                $container->get(ConfigInterface::class),
            );
        });

        // 3. Events an die Listener mappen
        $dispatcher = $container->get(EventDispatcherInterface::class);

        $dispatcher->addListener(PermitCreatedEvent::class, function ($event) use ($container): void {
            $container->get(SendPermitMailListener::class)->handle($event);
        });

        $dispatcher->addListener(PermitCancelledEvent::class, function ($event) use ($container): void {
            $container->get(SendPermitCancelledMailListener::class)->handle($event);
        });

        $dispatcher->addListener(VerificationRequestedEvent::class, function ($event) use ($container): void {
            $container->get(SendVerificationMailListener::class)->handle($event);
        });

        $dispatcher->addListener(MagicLinkRequestedEvent::class, function ($event) use ($container): void {
            $container->get(SendMagicLinkMailListener::class)->handle($event);
        });

        $dispatcher->addListener(GroupDeletedEvent::class, function ($event) use ($container): void {
            $container->get(DeleteGroupImageListener::class)->handle($event);
        });
    }
}
