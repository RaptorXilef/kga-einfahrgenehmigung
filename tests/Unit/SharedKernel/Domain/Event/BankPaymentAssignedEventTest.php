<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Domain\Event;

use App\SharedKernel\Domain\Event\BankPaymentAssignedEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BankPaymentAssignedEvent::class)]
final class BankPaymentAssignedEventTest extends TestCase
{
    #[Test]
    public function itStoresEventPayloadAndReturnsOccurredOnTimestamp(): void
    {
        $now = new DateTimeImmutable('2026-10-08 15:30:00');
        $event = new BankPaymentAssignedEvent(
            permitCode: 'ZM-0042-B-1234',
            reason: 'Automatisch via Bank-Import freigeschaltet',
            bookingDate: '08.10.2026',
            occurredOn: $now,
        );

        $this->assertSame('ZM-0042-B-1234', $event->permitCode);
        $this->assertSame('Automatisch via Bank-Import freigeschaltet', $event->reason);
        $this->assertSame('08.10.2026', $event->bookingDate);
        $this->assertSame($now, $event->occurredOn);
        $this->assertSame($now, $event->getOccurredOn());
    }
}
