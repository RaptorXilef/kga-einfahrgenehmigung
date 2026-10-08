<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Voucher\Domain;

use App\Modules\Voucher\Domain\Voucher;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Voucher::class)]
final class VoucherTest extends TestCase
{
    #[Test]
    public function itCreatesAValidVoucherWithCorrectInitialState(): void
    {
        $now = new DateTimeImmutable('2026-10-04 12:00:00');
        $voucher = Voucher::create(
            'TEST-CODE',
            'std_7',
            'Rabattaktion',
            'percent',
            50.0,
            false,
            1,
            null,
            ['parzelle' => '123'],
            'usr_admin',
            $now,
        );

        $this->assertSame('TEST-CODE', $voucher->code);
        $this->assertSame('std_7', $voucher->templateKey);
        $this->assertSame('Rabattaktion', $voucher->reason);
        $this->assertSame('percent', $voucher->type);
        $this->assertEqualsWithDelta(50.0, $voucher->value, \PHP_FLOAT_EPSILON);
        $this->assertFalse($voucher->isMultiUse);
        $this->assertSame(1, $voucher->maxUses);
        $this->assertSame(0, $voucher->getCurrentUses());
        $this->assertNull($voucher->expiresAt);
        $this->assertSame('aktiv', $voucher->getStatus());
        $this->assertSame(['parzelle' => '123'], $voucher->prefillData);
        $this->assertSame('usr_admin', $voucher->createdBy);
        $this->assertSame($now, $voucher->createdAt);
        $this->assertTrue($voucher->isActive());
        $this->assertFalse($voucher->isDeactivated());
        $this->assertFalse($voucher->isExpired($now));
    }

    #[Test]
    public function itHandlesStatusToggles(): void
    {
        $voucher = Voucher::create('C', 'T', 'R', 'F', 0, false, 1, null, [], 'U', new DateTimeImmutable());

        $voucher->deactivate();
        $this->assertTrue($voucher->isDeactivated());
        $this->assertSame('deaktiviert', $voucher->getStatus());

        $voucher->activate();
        $this->assertTrue($voucher->isActive());
        $this->assertSame('aktiv', $voucher->getStatus());
    }

    #[Test]
    public function itEvaluatesExpirationCorrectly(): void
    {
        $expires = new DateTimeImmutable('2026-10-05 12:00:00');
        $voucher = Voucher::create('C', 'T', 'R', 'F', 0, false, 1, $expires, [], 'U', new DateTimeImmutable());

        $beforeExp = new DateTimeImmutable('2026-10-05 11:59:59');
        // KILLT MUTANTE 6: Prüft auf exakte Zeitübereinstimmung (< vs <=)
        $exactExp = new DateTimeImmutable('2026-10-05 12:00:00');
        $afterExp = new DateTimeImmutable('2026-10-05 12:00:01');

        $this->assertFalse($voucher->isExpired($beforeExp));
        $this->assertFalse($voucher->isExpired($exactExp));
        $this->assertTrue($voucher->isExpired($afterExp));
    }

    #[Test]
    public function itEnforcesUsageLimitsForSingleUseVouchers(): void
    {
        $voucher = Voucher::create('C', 'T', 'R', 'F', 0, false, 1, null, [], 'U', new DateTimeImmutable());

        $voucher->recordUsage(); // Use 1
        $this->assertSame(1, $voucher->getCurrentUses());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Dieser Einmal-Gutschein wurde bereits verwendet.');

        $voucher->recordUsage(); // Use 2 throws
    }

    #[Test]
    public function itEnforcesUsageLimitsForMultiUseVouchers(): void
    {
        $voucher = Voucher::create('C', 'T', 'R', 'F', 0, true, 2, null, [], 'U', new DateTimeImmutable());

        $voucher->recordUsage(); // Use 1
        $voucher->recordUsage(); // Use 2
        $this->assertSame(2, $voucher->getCurrentUses());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Das maximale Nutzungslimit für diesen Gutschein ist erreicht.');

        $voucher->recordUsage(); // Use 3 throws
    }
}
