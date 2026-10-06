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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Permit::class)]
final class PermitTest extends TestCase
{
    private function createTestPermit(\DateTimeImmutable $start, \DateTimeImmutable $end, PermitStatus $status = PermitStatus::Offen): Permit
    {
        return new Permit(
            code: new PermitCode('TEST-1234'),
            template_key: new TemplateKey('std_7'),
            owner: new Owner('Max Mustermann', new EmailAddress('test@example.com'), new PlotNumber(42)),
            vehicle: new Vehicle('pkw', new LicensePlate('B-XX 123'), 'Test Firma'),
            validity: new Validity($start, $end, new Price(10.0), 'Bau'),
            status: new Status($status),
            erstellt: new \DateTimeImmutable('2026-10-01 10:00:00'),
        );
    }

    #[Test]
    public function it_initializes_correctly_and_returns_data_via_getters(): void
    {
        $start = new \DateTimeImmutable('2026-10-10');
        $end = new \DateTimeImmutable('2026-10-17');
        $permit = $this->createTestPermit($start, $end);

        self::assertSame('TEST-1234', $permit->code->value);
        self::assertSame('Max Mustermann', $permit->getOwnerName());
        self::assertSame('0042', $permit->getPlotNumber());
        self::assertSame('test@example.com', $permit->getOwnerEmail());
        self::assertSame('B-XX 123', $permit->getLicensePlate());
        self::assertSame('Test Firma', $permit->getCompany());
        self::assertSame(10.0, $permit->getPrice());
        self::assertFalse($permit->isPaid());
        self::assertFalse($permit->isSuspended());
    }

    #[Test]
    public function it_handles_payment_status_transitions(): void
    {
        $permit = $this->createTestPermit(new \DateTimeImmutable(), new \DateTimeImmutable());

        $paymentDate = new \DateTimeImmutable('2026-10-05 14:00:00');
        $permit->markAsPaid('PayPal Transaktion', $paymentDate);

        self::assertTrue($permit->isPaid());
        self::assertSame(PermitStatus::Bezahlt, $permit->getStatus());
        self::assertSame('PayPal Transaktion', $permit->getInternalComment());
        self::assertEquals($paymentDate, $permit->getPaidAt());
    }

    #[Test]
    public function it_handles_suspension_logic(): void
    {
        $permit = $this->createTestPermit(new \DateTimeImmutable(), new \DateTimeImmutable());

        $permit->suspend('Gartenordnung verletzt');
        self::assertTrue($permit->isSuspended());
        self::assertSame('Gartenordnung verletzt', $permit->getSuspensionReason());

        $permit->unsuspend();
        self::assertFalse($permit->isSuspended());
        self::assertNull($permit->getSuspensionReason());
    }

    #[Test]
    public function it_records_payment_reminders(): void
    {
        $permit = $this->createTestPermit(new \DateTimeImmutable(), new \DateTimeImmutable());

        $now = new \DateTimeImmutable('2026-10-05 10:00:00');
        $permit->recordReminder($now);

        self::assertEquals($now, $permit->getStatusObject()->last_reminder_at);
    }

    #[Test]
    public function it_validates_time_validity_correctly(): void
    {
        $start = new \DateTimeImmutable('2026-10-10 00:00:00');
        $end = new \DateTimeImmutable('2026-10-17 00:00:00');
        $permit = $this->createTestPermit($start, $end);

        $before = new \DateTimeImmutable('2026-10-09 23:59:59');
        $inside = new \DateTimeImmutable('2026-10-12 12:00:00');
        $after = new \DateTimeImmutable('2026-10-18 00:00:01');
        $exactEnd = new \DateTimeImmutable('2026-10-17 23:59:59');

        // Without payment requirement
        self::assertFalse($permit->isValid(false, $before));
        self::assertTrue($permit->isValid(false, $inside));
        self::assertTrue($permit->isValid(false, $exactEnd));
        self::assertFalse($permit->isValid(false, $after));
        self::assertTrue($permit->isExpired($after));
        self::assertTrue($permit->isFuture($before));

        // With payment requirement (permit is currently 'Offen')
        self::assertFalse($permit->isValid(true, $inside));

        // Pay it, then it should be valid
        $permit->markAsPaid();
        self::assertTrue($permit->isValid(true, $inside));
    }
}
