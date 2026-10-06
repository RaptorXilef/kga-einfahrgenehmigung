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
    public function itSuccessfullyMarksAnOpenPermitAsPaidAndSavesIt(): void
    {
        $permit = $this->createUnpaidPermitForHandler();

        $repository = $this->createMock(PermitRepositoryInterface::class);
        $repository->expects($this->once())->method('findByCode')->with('TEST-1234')->willReturn($permit);
        $repository->expects($this->once())->method('save')->with($permit);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-04 12:00:00'));

        $handler = new MarkPermitAsPaidHandler($repository, $clock);
        $handler->handle(new MarkPermitAsPaidCommand('TEST-1234', 'Barzahlung', '04.10.2026'));

        $this->assertTrue($permit->isPaid());
        $this->assertSame('Barzahlung', $permit->getInternalComment());
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
}
