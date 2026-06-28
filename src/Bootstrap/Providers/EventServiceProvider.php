<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Application\Listener\DeleteGroupImageListener;
use App\Application\Listener\SendMagicLinkMailListener;
use App\Application\Listener\SendPaymentReminderMailListener;
use App\Application\Listener\SendPermitCancelledMailListener;
use App\Application\Listener\SendPermitMailListener;
use App\Application\Listener\SendVerificationMailListener;
use App\Bootstrap\Container;
use App\Contracts\Event\EventDispatcherInterface;
use App\Core\Event\GroupDeletedEvent;
use App\Core\Event\MagicLinkRequestedEvent;
use App\Core\Event\PaymentReminderEvent;
use App\Core\Event\PermitCancelledEvent;
use App\Core\Event\PermitCreatedEvent;
use App\Core\Event\VerificationRequestedEvent;
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
        // Wir binden nur das Interface
        $container->bind(EventDispatcherInterface::class, fn () => new EventDispatcher());

        $dispatcher = $container->get(EventDispatcherInterface::class);

        // Der Container baut die konkreten Listener vollautomatisch (Autowiring) zusammen!
        $dispatcher->addListener(PermitCreatedEvent::class, fn ($event) => $container->get(
            SendPermitMailListener::class,
        )->handle($event));
        $dispatcher->addListener(VerificationRequestedEvent::class, fn ($event) => $container->get(
            SendVerificationMailListener::class,
        )->handle($event));
        $dispatcher->addListener(MagicLinkRequestedEvent::class, fn ($event) => $container->get(
            SendMagicLinkMailListener::class,
        )->handle($event));
        $dispatcher->addListener(GroupDeletedEvent::class, fn ($event) => $container->get(
            DeleteGroupImageListener::class,
        )->handle($event));
        $dispatcher->addListener(PaymentReminderEvent::class, fn ($event) => $container->get(
            SendPaymentReminderMailListener::class,
        )->handle($event));
        $dispatcher->addListener(PermitCancelledEvent::class, fn ($event) => $container->get(
            SendPermitCancelledMailListener::class,
        )->handle($event));
    }
}
