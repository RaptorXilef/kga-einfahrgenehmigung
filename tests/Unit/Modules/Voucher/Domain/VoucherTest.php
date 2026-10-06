<?php
declare(strict_types=1);
namespace App\Tests\Unit\Modules\Voucher\Domain;

use App\Modules\Voucher\Domain\Voucher;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Voucher::class)]
final class VoucherTest extends TestCase
{
    #[Test]
    public function it_creates_a_valid_voucher_with_correct_initial_state(): void
    {
        $now = new \DateTimeImmutable('2026-10-04 12:00:00');
        $voucher = Voucher::create(
            'TEST-CODE', 'std_7', 'Rabattaktion', 'percent', 50.0, false, 1, null, ['parzelle' => '123'], 'usr_admin', $now
        );

        self::assertSame('TEST-CODE', $voucher->code);
        self::assertSame(0, $voucher->getCurrentUses());
        self::assertTrue($voucher->isActive());
        self::assertFalse($voucher->isDeactivated());
        self::assertFalse($voucher->isExpired($now));
    }

    #[Test]
    public function it_handles_status_toggles(): void
    {
        $voucher = Voucher::create('C', 'T', 'R', 'F', 0, false, 1, null, [], 'U', new \DateTimeImmutable());

        $voucher->deactivate();
        self::assertTrue($voucher->isDeactivated());

        $voucher->activate();
        self::assertTrue($voucher->isActive());
    }

    #[Test]
    public function it_evaluates_expiration_correctly(): void
    {
        $expires = new \DateTimeImmutable('2026-10-05 12:00:00');
        $voucher = Voucher::create('C', 'T', 'R', 'F', 0, false, 1, $expires, [], 'U', new \DateTimeImmutable());

        $beforeExp = new \DateTimeImmutable('2026-10-05 11:59:59');
        $afterExp = new \DateTimeImmutable('2026-10-05 12:00:01');

        self::assertFalse($voucher->isExpired($beforeExp));
        self::assertTrue($voucher->isExpired($afterExp));
    }

    #[Test]
    public function it_enforces_usage_limits_for_single_use_vouchers(): void
    {
        $voucher = Voucher::create('C', 'T', 'R', 'F', 0, false, 1, null, [], 'U', new \DateTimeImmutable());

        $voucher->recordUsage(); // Use 1
        self::assertSame(1, $voucher->getCurrentUses());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Dieser Einmal-Gutschein wurde bereits verwendet.');

        $voucher->recordUsage(); // Use 2 throws
    }

    #[Test]
    public function it_enforces_usage_limits_for_multi_use_vouchers(): void
    {
        $voucher = Voucher::create('C', 'T', 'R', 'F', 0, true, 2, null, [], 'U', new \DateTimeImmutable());

        $voucher->recordUsage(); // Use 1
        $voucher->recordUsage(); // Use 2
        self::assertSame(2, $voucher->getCurrentUses());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Das maximale Nutzungslimit für diesen Gutschein ist erreicht.');

        $voucher->recordUsage(); // Use 3 throws
    }
}
