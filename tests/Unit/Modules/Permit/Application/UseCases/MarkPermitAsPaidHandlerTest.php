<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Application\UseCases;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\UseCases\MarkPermitAsPaid\MarkPermitAsPaidCommand;
use App\Modules\Permit\Application\UseCases\MarkPermitAsPaid\MarkPermitAsPaidHandler;
use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\Status;
use App\Modules\Permit\Domain\Validity;
use App\Modules\Permit\Domain\Vehicle;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PermitCode;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MarkPermitAsPaidHandler::class)]
final class MarkPermitAsPaidHandlerTest extends TestCase
{
    private function createUnpaidPermitForHandler(): Permit
    {
        return new Permit(
            code: new PermitCode('TEST-1234'),
            template_key: new TemplateKey('std_7'),
            owner: new Owner('Max Mustermann', null, new PlotNumber(42)),
            vehicle: new Vehicle('pkw', new LicensePlate('B-XX 123')),
            validity: new Validity(new DateTimeImmutable(), new DateTimeImmutable(), new Price(10.0), 'Privat'),
            status: new Status(PermitStatus::Offen),
            erstellt: new DateTimeImmutable(),
        );
    }

    #[Test]
    #[DataProvider('bookingDateProvider')]
    public function itHandlesDifferentBookingDateFormats(
        ?string $inputDate,
        string $expectedDateString,
    ): void {
        $permit = $this->createUnpaidPermitForHandler();

        $repository = $this->createMock(PermitRepositoryInterface::class);
        $repository->expects($this->once())->method('findByCode')->with('TEST-1234')->willReturn($permit);
        $repository->expects($this->once())->method('save')->with($permit);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-04 12:00:00'));

        $handler = new MarkPermitAsPaidHandler($repository, $clock);

        $handler->handle(new MarkPermitAsPaidCommand('TEST-1234', 'Grund', $inputDate));

        $this->assertTrue($permit->isPaid());
        $this->assertSame($expectedDateString, $permit->getPaidAt()->format('Y-m-d H:i:s'));
    }

    public static function bookingDateProvider(): array
    {
        return [
            // Durch das "!" im Handler ist die Zeit hier nun deterministisch auf 00:00:00 genullt
            'long year format' => ['05.10.2026', '2026-10-05 00:00:00'],
            'short year format' => ['05.10.26', '2026-10-05 00:00:00'],
            // KILLT MUTANTEN 2 & 3: Erzwingt das trim() vor createFromFormat
            'long year format with spaces' => ['  05.10.2026  ', '2026-10-05 00:00:00'],
            'short year format with spaces' => ['  05.10.26  ', '2026-10-05 00:00:00'],
            // Der Fallback nutzt unser sauberes ClockInterface Mock (12:00:00)
            'invalid string falls back to now' => ['Kartoffelsalat', '2026-10-04 12:00:00'],
            'null falls back to now' => [null, '2026-10-04 12:00:00'],
        ];
    }

    #[Test]
    public function itThrowsAnExceptionIfThePermitToPayDoesNotExist(): void
    {
        $repository = $this->createStub(PermitRepositoryInterface::class);
        $repository->method('findByCode')->willReturn(null);
        $clock = $this->createStub(ClockInterface::class);

        $handler = new MarkPermitAsPaidHandler($repository, $clock);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Genehmigung UNKNOWN nicht gefunden.');

        $handler->handle(new MarkPermitAsPaidCommand('UNKNOWN'));
    }

    #[Test]
    public function itAppendsTheReasonIfACommentAlreadyExists(): void
    {
        $permit = new Permit(
            new PermitCode('TEST-1234'),
            new TemplateKey('std_7'),
            new Owner('Name', null, new PlotNumber(42)),
            new Vehicle('pkw', new LicensePlate('B-XX 123')),
            new Validity(new DateTimeImmutable(), new DateTimeImmutable(), new Price(10.0), 'Privat'),
            new Status(PermitStatus::Offen),
            new DateTimeImmutable(),
            'Alter Kommentar',
        );

        $repository = $this->createStub(PermitRepositoryInterface::class);
        $repository->method('findByCode')->willReturn($permit);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-04 12:00:00'));

        $handler = new MarkPermitAsPaidHandler($repository, $clock);
        $handler->handle(new MarkPermitAsPaidCommand('TEST-1234', 'Neuer Grund'));

        // Killt den String-Concat Mutanten!
        $this->assertSame('Alter Kommentar | Neuer Grund', $permit->getInternalComment());
    }

    #[Test]
    public function itDoesNotAppendTheReasonIfItAlreadyExists(): void
    {
        // KILLT MUTANTE 4: Verhindert doppelte Gründe (str_contains)
        $permit = new Permit(
            new PermitCode('TEST-1234'),
            new TemplateKey('std_7'),
            new Owner('Name', null, new PlotNumber(42)),
            new Vehicle('pkw', new LicensePlate('B-XX 123')),
            new Validity(new DateTimeImmutable(), new DateTimeImmutable(), new Price(10.0), 'Privat'),
            new Status(PermitStatus::Offen),
            new DateTimeImmutable(),
            'Grund',
        );

        $repository = $this->createStub(PermitRepositoryInterface::class);
        $repository->method('findByCode')->willReturn($permit);
        $clock = $this->createStub(ClockInterface::class);

        $handler = new MarkPermitAsPaidHandler($repository, $clock);
        $handler->handle(new MarkPermitAsPaidCommand('TEST-1234', 'Grund'));

        $this->assertSame('Grund', $permit->getInternalComment());
    }
}
