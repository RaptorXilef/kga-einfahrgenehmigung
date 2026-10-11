<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Voucher\Application\UseCases;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Voucher\Application\UseCases\CheckAvailableVouchers\CheckAvailableVouchersHandler;
use App\Modules\Voucher\Application\UseCases\CheckAvailableVouchers\CheckAvailableVouchersQuery;
use App\Modules\Voucher\Application\UseCases\GetVoucherPrefill\GetVoucherPrefillHandler;
use App\Modules\Voucher\Application\UseCases\GetVoucherPrefill\GetVoucherPrefillQuery;
use App\Modules\Voucher\Application\UseCases\GetVoucherPrefill\VoucherPrefillDto;
use DateTimeImmutable;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckAvailableVouchersHandler::class)]
#[CoversClass(GetVoucherPrefillHandler::class)]
final class VoucherQueryHandlersTest extends TestCase
{
    private function getPdoStub(mixed $returnFromFetchColumn, ?array $returnFromFetchRow = null): PDO
    {
        $stmt = $this->createStub(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn($returnFromFetchColumn);
        $stmt->method('fetch')->willReturn($returnFromFetchRow ?? false);

        $pdo = $this->createStub(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }

    #[Test]
    public function itReturnsTrueIfAvailableVouchersExist(): void
    {
        $pdo = $this->getPdoStub('1'); // SQL liefert true/1
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable());

        $handler = new CheckAvailableVouchersHandler($pdo, $clock);
        $this->assertTrue($handler->handle(new CheckAvailableVouchersQuery()));
    }

    #[Test]
    public function itReturnsFalseIfNoAvailableVouchersExist(): void
    {
        $pdo = $this->getPdoStub(false); // SQL liefert nichts
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable());

        $handler = new CheckAvailableVouchersHandler($pdo, $clock);
        $this->assertFalse($handler->handle(new CheckAvailableVouchersQuery()));
    }

    #[Test]
    public function itReturnsPrefillDtoForValidVoucher(): void
    {
        $pdo = $this->getPdoStub(false, [
            'status' => 'aktiv',
            'code' => 'V-123',
            'reason' => 'Test',
            'template_key' => 'std_14',
            'is_multi_use' => 0,
            'current_uses' => 0,
            'prefill_data' => '{"name":"Max"}',
        ]);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15 12:00:00'));

        $handler = new GetVoucherPrefillHandler($pdo, $clock);
        $dto = $handler->handle(new GetVoucherPrefillQuery('V-123'));

        $this->assertInstanceOf(VoucherPrefillDto::class, $dto);
        $this->assertSame('V-123', $dto->code);
        $this->assertSame('std_14', $dto->templateKey);
        $this->assertSame(['name' => 'Max'], $dto->data);
    }

    #[Test]
    public function itReturnsNullIfVoucherIsInvalidOrDepleted(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15 12:00:00'));

        // Fall 1: Deaktiviert
        $pdoDeac = $this->getPdoStub(false, ['status' => 'deaktiviert']);
        $handler1 = new GetVoucherPrefillHandler($pdoDeac, $clock);
        $this->assertNull($handler1->handle(new GetVoucherPrefillQuery('V-123')));

        // Fall 2: Abgelaufen
        $pdoExp = $this->getPdoStub(false, ['status' => 'aktiv', 'expires_at' => '2026-10-14 12:00:00']);
        $handler2 = new GetVoucherPrefillHandler($pdoExp, $clock);
        $this->assertNull($handler2->handle(new GetVoucherPrefillQuery('V-123')));

        // Fall 3: Aufgebraucht (Single Use)
        $pdoDep = $this->getPdoStub(false, ['status' => 'aktiv', 'is_multi_use' => 0, 'current_uses' => 1]);
        $handler3 = new GetVoucherPrefillHandler($pdoDep, $clock);
        $this->assertNull($handler3->handle(new GetVoucherPrefillQuery('V-123')));
    }
}
