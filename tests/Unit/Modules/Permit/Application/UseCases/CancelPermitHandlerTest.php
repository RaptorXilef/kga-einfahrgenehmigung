<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Application\UseCases;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\UseCases\CancelPermit\CancelPermitCommand;
use App\Modules\Permit\Application\UseCases\CancelPermit\CancelPermitHandler;
use App\Modules\Permit\Domain\CancelledPermitRepositoryInterface;
use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
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
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CancelPermitHandler::class)]
final class CancelPermitHandlerTest extends TestCase
{
    private function getConfigStub(bool $allowCancellation): ConfigInterface
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBool')->willReturnMap([['allow_user_cancellation', true, $allowCancellation]]);

        return $config;
    }

    #[Test]
    public function itCancelsFutureUnpaidPermitsAndArchivesThem(): void
    {
        $permit = new Permit(
            new PermitCode('T-123'),
            new TemplateKey('std_7'),
            new Owner('Max', new EmailAddress('test@example.com'), new PlotNumber(42)),
            new Vehicle('pkw', new LicensePlate('B')),
            new Validity(new DateTimeImmutable('2026-10-20'), new DateTimeImmutable('2026-10-25'), new Price(10.0), ''),
            new Status(PermitStatus::Offen),
            new DateTimeImmutable(),
        );

        $repo = $this->createMock(PermitRepositoryInterface::class);
        $repo->expects($this->once())->method('findByCode')->with('T-123')->willReturn($permit); // N-Fix
        $repo->expects($this->once())->method('delete')->with('T-123'); // Active Permit must be deleted

        $cancelledRepo = $this->createMock(CancelledPermitRepositoryInterface::class);
        $cancelledRepo->expects($this->once())->method('saveCancelled')->willReturnCallback(
            function (Permit $p): void {
                // Must be anonymized!
                $this->assertSame('[ANONYMISIERT]', $p->getOwnerName());
                $this->assertSame('XXX-XX 9999', $p->getLicensePlate());
                $this->assertSame(PermitStatus::Storniert, $p->getStatus());
            },
        );

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15 12:00:00'));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch'); // PermitCancelledEvent

        $handler = new CancelPermitHandler($this->getConfigStub(true), $repo, $cancelledRepo, $clock, $dispatcher);
        $handler->handle(new CancelPermitCommand('T-123', 'test@example.com'));
    }

    #[Test]
    public function itThrowsExceptionIfCancellationIsDisabled(): void
    {
        $handler = new CancelPermitHandler($this->getConfigStub(false), $this->createStub(PermitRepositoryInterface::class), $this->createStub(CancelledPermitRepositoryInterface::class), $this->createStub(ClockInterface::class), $this->createStub(EventDispatcherInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stornierungen sind derzeit deaktiviert.');

        $handler->handle(new CancelPermitCommand('T', 'e@e.de'));
    }

    #[Test]
    public function itThrowsExceptionIfPermitIsAlreadyPaid(): void
    {
        $permit = new Permit(
            new PermitCode('T-123'),
            new TemplateKey('std_7'),
            new Owner('Max', new EmailAddress('test@example.com'), new PlotNumber(42)),
            new Vehicle('pkw', new LicensePlate('B')),
            new Validity(new DateTimeImmutable('2026-10-20'), new DateTimeImmutable('2026-10-25'), new Price(10.0), ''),
            new Status(PermitStatus::Bezahlt), // BEZAHLT!
            new DateTimeImmutable(),
        );

        $repo = $this->createStub(PermitRepositoryInterface::class);
        $repo->method('findByCode')->willReturn($permit);

        $handler = new CancelPermitHandler($this->getConfigStub(true), $repo, $this->createStub(CancelledPermitRepositoryInterface::class), $this->createStub(ClockInterface::class), $this->createStub(EventDispatcherInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Bereits bezahlte Genehmigungen können nicht automatisch storniert werden.');

        $handler->handle(new CancelPermitCommand('T', 'test@example.com'));
    }
}
