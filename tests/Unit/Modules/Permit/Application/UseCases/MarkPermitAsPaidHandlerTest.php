<?php

declare(strict_types=1);

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

\covers(MarkPermitAsPaidHandler::class);

function createUnpaidPermitForHandler(): Permit
{
    return new Permit(
        code: new PermitCode('TEST-1234'),
        template_key: new TemplateKey('std_7'),
        owner: new Owner('Max Mustermann', null, new PlotNumber(42)),
        vehicle: new Vehicle('pkw', new LicensePlate('B-XX 123')),
        validity: new Validity(new \DateTimeImmutable(), new \DateTimeImmutable(), new Price(10.0), 'Privat'),
        status: new Status(PermitStatus::Offen),
        erstellt: new \DateTimeImmutable(),
    );
}

\test('it successfully marks an open permit as paid and saves it', function (): void {
    // 1. Arrange: Wir erstellen unsere Mocks
    $permit = \createUnpaidPermitForHandler();

    /** @var PermitRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository */
    $repository = $this->createMock(PermitRepositoryInterface::class);

    // Wir erwarten, dass der Handler exakt 1x findByCode aufruft und unser Fake-Permit erhält
    $repository->expects($this->once())
        ->method('findByCode')
        ->with('TEST-1234')
        ->willReturn($permit);

    // Wir erwarten, dass der Handler am Ende save() aufruft
    $repository->expects($this->once())
        ->method('save')
        ->with($permit);

    /** @var ClockInterface&\PHPUnit\Framework\MockObject\Stub $clock */
    $clock = $this->createStub(ClockInterface::class);
    $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-04 12:00:00'));

    $handler = new MarkPermitAsPaidHandler($repository, $clock);

    // 2. Act: Wir jagen das Command rein
    $handler->handle(new MarkPermitAsPaidCommand('TEST-1234', 'Barzahlung', '04.10.2026'));

    // 3. Assert: Hat der Handler den Status des Objekts verändert, bevor er save() aufrief?
    \expect($permit->isPaid())->toBeTrue()
        ->and($permit->getInternalComment())->toBe('Barzahlung');
});

\test('it throws an exception if the permit to pay does not exist', function (): void {
    /** @var PermitRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $repository */
    $repository = $this->createStub(PermitRepositoryInterface::class);
    $repository->method('findByCode')->willReturn(null);

    /** @var ClockInterface&\PHPUnit\Framework\MockObject\Stub $clock */
    $clock = $this->createStub(ClockInterface::class);

    $handler = new MarkPermitAsPaidHandler($repository, $clock);

    // Das Ausführen des Commands mit einem unbekannten Code muss sofort knallen
    $handler->handle(new MarkPermitAsPaidCommand('UNKNOWN'));
})->throws(\DomainException::class, 'Genehmigung UNKNOWN nicht gefunden.');
