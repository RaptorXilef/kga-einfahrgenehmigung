<?php

declare(strict_types=1);

namespace App\Modules\Permit\Application\Listeners;

use App\Modules\Permit\Application\UseCases\MarkPermitAsPaid\MarkPermitAsPaidCommand;
use App\Modules\Permit\Application\UseCases\MarkPermitAsPaid\MarkPermitAsPaidHandler;
use App\SharedKernel\Domain\Event\BankPaymentAssignedEvent;

/**
 * Lauscht auf Integration Events vom Finance Modul.
 * Sorgt dafür, dass die Domain Logik zum Bezahlen eines Permits sauber im Permit Modul ausgeführt wird.
 */
final readonly class MarkPermitPaidOnBankPaymentListener
{
    public function __construct(
        private MarkPermitAsPaidHandler $markPaidHandler,
    ) {
    }

    public function handle(BankPaymentAssignedEvent $event): void
    {
        $this->markPaidHandler->handle(new MarkPermitAsPaidCommand(
            $event->permitCode,
            $event->reason,
            $event->bookingDate,
        ));
    }
}
