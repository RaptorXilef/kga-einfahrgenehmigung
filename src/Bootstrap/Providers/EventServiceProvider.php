<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Contracts\Bootstrap\ServiceProviderInterface;
use App\Contracts\DependencyInjection\ContainerInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Modules\Identity\Application\Listeners\DeleteGroupImageListener;
use App\Modules\Identity\Application\Listeners\SendMagicLinkMailListener;
use App\Modules\Identity\Domain\Events\MagicLinkRequestedEvent;
use App\Modules\Identity\Domain\Events\RoleDeletedEvent;
use App\Modules\Permit\Application\Listeners\MarkPermitPaidOnBankPaymentListener;
use App\Modules\Permit\Application\Listeners\SendPaymentReminderMailListener;
use App\Modules\Permit\Application\Listeners\SendPermitCancelledMailListener;
use App\Modules\Permit\Application\Listeners\SendPermitMailListener;
use App\Modules\Permit\Application\Listeners\SendVerificationMailListener;
use App\Modules\Permit\Domain\Events\PaymentReminderEvent;
use App\Modules\Permit\Domain\Events\PermitCancelledEvent;
use App\Modules\Permit\Domain\Events\PermitCreatedEvent;
use App\Modules\Permit\Domain\Events\VerificationRequestedEvent;
use App\SharedKernel\Domain\Event\BankPaymentAssignedEvent;
use App\SharedKernel\Infrastructure\Event\EventDispatcher;
use Override;

/**
 * Zentraler Event-Verteiler-Provider. Verknüpft alle Domain-Events mit ihren Listenern.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class EventServiceProvider implements ServiceProviderInterface
{
    #[Override]
    public function register(ContainerInterface $container): void
    {
        // Wir binden nur das Interface
        $container->bind(EventDispatcherInterface::class, fn (): EventDispatcher => new EventDispatcher());

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

        $dispatcher->addListener(RoleDeletedEvent::class, fn ($event) => $container->get(
            DeleteGroupImageListener::class,
        )->handle($event));

        $dispatcher->addListener(PaymentReminderEvent::class, fn ($event) => $container->get(
            SendPaymentReminderMailListener::class,
        )->handle($event));

        $dispatcher->addListener(PermitCancelledEvent::class, fn ($event) => $container->get(
            SendPermitCancelledMailListener::class,
        )->handle($event));

        // Cross-Module Integration Event (Finance -> Permit)
        $dispatcher->addListener(BankPaymentAssignedEvent::class, fn ($event) => $container->get(
            MarkPermitPaidOnBankPaymentListener::class,
        )->handle($event));
    }
}
