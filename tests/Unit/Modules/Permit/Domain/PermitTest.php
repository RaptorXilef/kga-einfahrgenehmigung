<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Domain;

use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\Status;
use App\Modules\Permit\Domain\Validity;
use App\Modules\Permit\Domain\Vehicle;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PermitCode;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Permit::class)]
final class PermitTest extends TestCase
{
    private function createTestPermit(DateTimeImmutable $start, DateTimeImmutable $end, PermitStatus $status = PermitStatus::Offen): Permit
    {
        return new Permit(
            code: new PermitCode('TEST-1234'),
            template_key: new TemplateKey('std_7'),
            owner: new Owner('Max Mustermann', new EmailAddress('test@example.com'), new PlotNumber(42)),
            vehicle: new Vehicle('pkw', new LicensePlate('B-XX 123'), 'Test Firma'),
            validity: new Validity($start, $end, new Price(10.0), 'Bau'),
            status: new Status($status),
            erstellt: new DateTimeImmutable('2026-10-01 10:00:00'),
        );
    }

    #[Test]
    public function itInitializesCorrectlyAndReturnsDataViaGetters(): void
    {
        $start = new DateTimeImmutable('2026-10-10');
        $end = new DateTimeImmutable('2026-10-17');
        $permit = $this->createTestPermit($start, $end);

        $this->assertSame('TEST-1234', $permit->code->value);
        $this->assertSame('Max Mustermann', $permit->getOwnerName());
        $this->assertSame('0042', $permit->getPlotNumber());
        $this->assertSame('test@example.com', $permit->getOwnerEmail());
        $this->assertSame('B-XX 123', $permit->getLicensePlate());
        $this->assertSame('Test Firma', $permit->getCompany());
        $this->assertEqualsWithDelta(10.0, $permit->getPrice(), \PHP_FLOAT_EPSILON);
        $this->assertFalse($permit->isPaid());
        $this->assertFalse($permit->isSuspended());
    }

    #[Test]
    public function itHandlesPaymentStatusTransitions(): void
    {
        $permit = $this->createTestPermit(new DateTimeImmutable(), new DateTimeImmutable());

        $paymentDate = new DateTimeImmutable('2026-10-05 14:00:00');
        $permit->markAsPaid('PayPal Transaktion', $paymentDate);

        $this->assertTrue($permit->isPaid());
        $this->assertSame(PermitStatus::Bezahlt, $permit->getStatus());
        $this->assertSame('PayPal Transaktion', $permit->getInternalComment());
        $this->assertEquals($paymentDate, $permit->getPaidAt());
    }

    #[Test]
    public function itHandlesSuspensionLogic(): void
    {
        $permit = $this->createTestPermit(new DateTimeImmutable(), new DateTimeImmutable());

        $permit->suspend('Gartenordnung verletzt');
        $this->assertTrue($permit->isSuspended());
        $this->assertSame('Gartenordnung verletzt', $permit->getSuspensionReason());

        $permit->unsuspend();
        $this->assertFalse($permit->isSuspended());
        $this->assertNull($permit->getSuspensionReason());
    }

    #[Test]
    public function itRecordsPaymentReminders(): void
    {
        $permit = $this->createTestPermit(new DateTimeImmutable(), new DateTimeImmutable());

        $now = new DateTimeImmutable('2026-10-05 10:00:00');
        $permit->recordReminder($now);

        $this->assertEquals($now, $permit->getStatusObject()->last_reminder_at);
    }

    #[Test]
    public function itValidatesTimeValidityCorrectly(): void
    {
        $start = new DateTimeImmutable('2026-10-10 00:00:00');
        $end = new DateTimeImmutable('2026-10-17 00:00:00');
        $permit = $this->createTestPermit($start, $end);

        // 1 Sekunde VOR Start
        $before = new DateTimeImmutable('2026-10-09 23:59:59');
        // Exakt auf die Sekunde beim Start
        $exactStart = new DateTimeImmutable('2026-10-10 00:00:00');
        $inside = new DateTimeImmutable('2026-10-12 12:00:00');
        // Exakt auf die letzte erlaubte Sekunde
        $exactEnd = new DateTimeImmutable('2026-10-17 23:59:59');
        // Exakt 1 Sekunde ZU SPÄT (Muss fehlschlagen!)
        $after = new DateTimeImmutable('2026-10-18 00:00:00');

        // Without payment requirement
        $this->assertFalse($permit->isValid(false, $before));
        $this->assertTrue($permit->isValid(false, $exactStart));
        $this->assertTrue($permit->isValid(false, $inside));

        // KILLT DEN >= / <= MUTANTEN!
        $this->assertTrue($permit->isValid(false, $exactEnd));
        $this->assertFalse($permit->isValid(false, $after));

        // Das ist der Bugfix!
        $this->assertTrue($permit->isExpired($after));
        $this->assertFalse($permit->isExpired($exactEnd));

        $this->assertTrue($permit->isFuture($before));
        $this->assertFalse($permit->isFuture($exactStart));

        // With payment requirement
        $this->assertFalse($permit->isValid(true, $inside));
        $permit->markAsPaid();
        $this->assertTrue($permit->isValid(true, $inside));
    }
}
