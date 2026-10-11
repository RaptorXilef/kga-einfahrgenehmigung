<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Voucher\Application\UseCases;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount\CalculateVoucherDiscountHandler;
use App\Modules\Voucher\Application\UseCases\CalculateVoucherDiscount\CalculateVoucherDiscountQuery;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CalculateVoucherDiscountHandler::class)]
final class CalculateVoucherDiscountHandlerTest extends TestCase
{
    private function getPdoStub(?array $rowToReturn): PDO
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetch')->willReturn($rowToReturn === null ? false : $rowToReturn);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    #[Test]
    public function itReturnsInvalidIfCodeDoesNotExist(): void
    {
        $pdo = $this->getPdoStub(null);
        $handler = new CalculateVoucherDiscountHandler($pdo, $this->createStub(ClockInterface::class));

        $result = $handler->handle(new CalculateVoucherDiscountQuery('UNKNOWN', 10.0));

        $this->assertFalse($result->isValid);
        $this->assertSame(10.0, $result->finalPrice);
        $this->assertSame('Ungültiger Code', $result->errorMessage);
    }

    #[Test]
    public function itReturnsInvalidIfCodeIsDeactivatedOrExpired(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15 12:00:00'));

        $pdoDeactivated = $this->getPdoStub(['status' => 'deaktiviert']);
        $handler1 = new CalculateVoucherDiscountHandler($pdoDeactivated, $clock);

        $result1 = $handler1->handle(new CalculateVoucherDiscountQuery('CODE', 10.0));
        $this->assertFalse($result1->isValid);
        $this->assertSame('Code gesperrt', $result1->errorMessage);

        $pdoExpired = $this->getPdoStub(['status' => 'aktiv', 'expires_at' => '2026-10-14 12:00:00']);
        $handler2 = new CalculateVoucherDiscountHandler($pdoExpired, $clock);

        $result2 = $handler2->handle(new CalculateVoucherDiscountQuery('CODE', 10.0));
        $this->assertFalse($result2->isValid);
        $this->assertSame('Code abgelaufen', $result2->errorMessage);
    }

    #[Test]
    public function itReturnsInvalidIfUsageLimitIsReached(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15 12:00:00'));

        // Single Use - Already Used
        $pdoDepletedSingle = $this->getPdoStub(['status' => 'aktiv', 'is_multi_use' => 0, 'current_uses' => 1, 'max_uses' => 1]);
        $handler1 = new CalculateVoucherDiscountHandler($pdoDepletedSingle, $clock);

        $result1 = $handler1->handle(new CalculateVoucherDiscountQuery('CODE', 10.0));
        $this->assertFalse($result1->isValid);
        $this->assertSame('Code aufgebraucht', $result1->errorMessage);

        // Multi Use - Max reached
        $pdoDepletedMulti = $this->getPdoStub(['status' => 'aktiv', 'is_multi_use' => 1, 'current_uses' => 5, 'max_uses' => 5]);
        $handler2 = new CalculateVoucherDiscountHandler($pdoDepletedMulti, $clock);

        $result2 = $handler2->handle(new CalculateVoucherDiscountQuery('CODE', 10.0));
        $this->assertFalse($result2->isValid);
        $this->assertSame('Code aufgebraucht', $result2->errorMessage);
    }

    #[Test]
    public function itCalculatesDiscountsCorrectly(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15 12:00:00'));

        // 1. FREE
        $pdoFree = $this->getPdoStub(['status' => 'aktiv', 'is_multi_use' => 0, 'current_uses' => 0, 'type' => 'free']);
        $handler1 = new CalculateVoucherDiscountHandler($pdoFree, $clock);
        $result1 = $handler1->handle(new CalculateVoucherDiscountQuery('CODE', 10.0));
        $this->assertTrue($result1->isValid);
        $this->assertSame(0.0, $result1->finalPrice);
        $this->assertSame('100% Rabatt (Kostenlos)', $result1->discountText);

        // 2. PERCENT (50%)
        $pdoPercent = $this->getPdoStub(['status' => 'aktiv', 'is_multi_use' => 0, 'current_uses' => 0, 'type' => 'percent', 'value' => 50.0]);
        $handler2 = new CalculateVoucherDiscountHandler($pdoPercent, $clock);
        $result2 = $handler2->handle(new CalculateVoucherDiscountQuery('CODE', 10.0));
        $this->assertTrue($result2->isValid);
        $this->assertSame(5.0, $result2->finalPrice);
        $this->assertSame('50% Rabatt', $result2->discountText);

        // 3. FIXED (-3€)
        $pdoFixed = $this->getPdoStub(['status' => 'aktiv', 'is_multi_use' => 0, 'current_uses' => 0, 'type' => 'fixed', 'value' => 3.0]);
        $handler3 = new CalculateVoucherDiscountHandler($pdoFixed, $clock);
        $result3 = $handler3->handle(new CalculateVoucherDiscountQuery('CODE', 10.0));
        $this->assertTrue($result3->isValid);
        $this->assertSame(7.0, $result3->finalPrice);
        $this->assertSame('Sonderpreis aktiviert', $result3->discountText);
    }
}
