<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Voucher\Application\UseCases;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Voucher\Application\UseCases\CreateVoucher\CreateVoucherCommand;
use App\Modules\Voucher\Application\UseCases\CreateVoucher\CreateVoucherHandler;
use App\Modules\Voucher\Domain\Voucher;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateVoucherHandler::class)]
final class CreateVoucherHandlerTest extends TestCase
{
    #[Test]
    public function itCreatesAndSavesNewVoucher(): void
    {
        $repo = $this->createMock(VoucherRepositoryInterface::class);
        // Code-Prüfung: Wir sagen, der Code ist noch nicht vergeben
        $repo->method('findByCode')->willReturn(null);

        $repo->expects($this->once())->method('save')->willReturnCallback(
            function (Voucher $voucher): void {
                $this->assertSame('V-EXTRA', $voucher->code);
                $this->assertSame('std_7', $voucher->templateKey);
                $this->assertSame('Rabattaktion', $voucher->reason);
                $this->assertSame('free', $voucher->type);
                $this->assertEqualsWithDelta(0.0, $voucher->value, \PHP_FLOAT_EPSILON);
                $this->assertTrue($voucher->isMultiUse);
                $this->assertSame(10, $voucher->maxUses);
                $this->assertSame('usr_123', $voucher->createdBy);
                $this->assertSame(['parzelle' => '42'], $voucher->prefillData);
            },
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable());

        $handler = new CreateVoucherHandler($repo, $clock);
        $handler->handle(new CreateVoucherCommand(
            'Rabattaktion',
            'usr_123',
            'std_7',
            ['parzelle' => '42'],
            'free',
            0.0,
            true,
            10,
            'V-EXTRA',
            null,
            'fixed',
        ));
    }

    #[Test]
    public function itThrowsExceptionIfCustomCodeAlreadyExists(): void
    {
        $repo = $this->createStub(VoucherRepositoryInterface::class);
        $repo->method('findByCode')->willReturn(Voucher::create('V-EXIST', 'std_7', 'Test', 'free', 0.0, false, 1, null, [], 'u', new DateTimeImmutable()));

        $handler = new CreateVoucherHandler($repo, $this->createStub(ClockInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Der Gutscheincode 'V-EXIST' existiert bereits.");

        $handler->handle(new CreateVoucherCommand('Test', 'usr_123', 'std_7', [], 'free', 0.0, false, 1, 'V-EXIST', null, 'fixed'));
    }

    #[Test]
    public function itThrowsExceptionIfValueIsNegative(): void
    {
        $handler = new CreateVoucherHandler(
            $this->createStub(VoucherRepositoryInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Der Rabattwert darf nicht negativ sein.');

        $handler->handle(new CreateVoucherCommand('Test', 'usr_1', 'std_7', [], 'fixed', -10.0, false, 1, '', null, 'fixed'));
    }
}
