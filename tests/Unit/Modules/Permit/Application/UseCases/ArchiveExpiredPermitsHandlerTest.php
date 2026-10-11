<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Application\UseCases;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\UseCases\ArchiveExpiredPermits\ArchiveExpiredPermitsCommand;
use App\Modules\Permit\Application\UseCases\ArchiveExpiredPermits\ArchiveExpiredPermitsHandler;
use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArchiveExpiredPermitsHandler::class)]
final class ArchiveExpiredPermitsHandlerTest extends TestCase
{
    #[Test]
    public function itChunksExpiredPermitsAndMovesThemToArchive(): void
    {
        $permit1 = new Permit(
            new PermitCode('T-1'),
            new TemplateKey('std_7'),
            new Owner('Max', null, new PlotNumber(42)),
            new Vehicle('pkw', new LicensePlate('B')),
            new Validity(new DateTimeImmutable(), new DateTimeImmutable(), new Price(0.0), ''),
            new Status(PermitStatus::Bezahlt),
            new DateTimeImmutable(),
        );
        $permit2 = clone $permit1; // Simuliere 2 Permits

        $repo = $this->createMock(PermitRepositoryInterface::class);
        $repo->expects($this->once())->method('yieldExpired')->willReturn((function () use ($permit1, $permit2) {
            yield $permit1;
            yield $permit2;
        })()); // N-Fix
        $repo->expects($this->once())->method('deleteMultiple')->with(['T-1', 'T-1']); // Delete wird einmal mit dem Array aufgerufen

        $archiveRepo = $this->createMock(PermitArchiveRepositoryInterface::class);
        $archiveRepo->expects($this->once())->method('archivePermits')->with(0, [$permit1, $permit2]);
        $archiveRepo->expects($this->once())->method('anonymizeOldRecords')->with(10)->willReturn(5); // 5 Datensätze anonymisiert

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15 12:00:00'));

        $handler = new ArchiveExpiredPermitsHandler($repo, $archiveRepo, $clock);
        $anonymizedCount = $handler->handle(new ArchiveExpiredPermitsCommand(graceDays: 0, anonymizeYearsThreshold: 10));

        $this->assertSame(5, $anonymizedCount);
    }
}
