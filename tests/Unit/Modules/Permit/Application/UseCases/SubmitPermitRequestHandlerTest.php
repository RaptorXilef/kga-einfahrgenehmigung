<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Application\UseCases;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\UseCases\SubmitPermitRequest\SubmitPermitRequestCommand;
use App\Modules\Permit\Application\UseCases\SubmitPermitRequest\SubmitPermitRequestHandler;
use App\Modules\Permit\Domain\Exceptions\PermitCollisionException;
use App\Modules\Permit\Domain\PermitFinancialCalculator;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\Modules\Permit\Domain\VerificationRequest;
use App\SharedKernel\Domain\ValueObject\EmailAddress;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SubmitPermitRequestHandler::class)]
final class SubmitPermitRequestHandlerTest extends TestCase
{
    private function getValidCommand(?string $editToken = null, ?string $sessionEmail = null): SubmitPermitRequestCommand
    {
        return new SubmitPermitRequestCommand(
            name: 'Max Mustermann',
            email: new EmailAddress('test@example.com'),
            parzelle: new PlotNumber(42),
            typ: 'pkw',
            kennzeichen: new LicensePlate('B-XX 123'),
            firma: null,
            zweck: 'Privat',
            templateKey: new TemplateKey('std_7'),
            datumVon: '2026-10-15',
            datumBis: '2026-10-20',
            agreements: [],
            voucher: null,
            editToken: $editToken,
            sessionEmail: $sessionEmail,
        );
    }

    #[Test]
    public function itCreatesNewPendingRequestWhenNoEditTokenIsProvided(): void
    {
        $permitRepo = $this->createStub(PermitRepositoryInterface::class);
        $permitRepo->method('hasCollision')->willReturn(false);

        $verifyRepo = $this->createMock(VerificationRepositoryInterface::class);
        $verifyRepo->method('loadPending')->willReturn([]);
        // Erwartet das Speichern in pending_verifications
        $verifyRepo->expects($this->once())->method('savePendingOne');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch'); // VerificationRequestedEvent

        $config = $this->createStub(ConfigInterface::class);
        $config->method('getArray')->willReturnMap([
            ['permit_templates', [], ['std_7' => ['prices' => ['pkw' => 10.0]]]],
            ['vehicle_types', [], ['pkw' => ['label' => 'PKW']]],
        ]);
        $config->method('get')->willReturnMap([['max_plot_number', 9999, 9999], ['hours_pending_verify', 24, 24]]);

        // Real Domain Service
        $calculator = new PermitFinancialCalculator($config, $this->createStub(ClockInterface::class));

        $handler = new SubmitPermitRequestHandler($config, new SystemClock(), $verifyRepo, $permitRepo, $eventDispatcher, $calculator);

        $token = $handler->handle($this->getValidCommand());
        $this->assertIsString($token);
        $this->assertSame(64, \strlen($token)); // 32 bytes hex
    }

    #[Test]
    public function itUpdatesVerifiedRequestIfEditTokenMatchesAndEmailIsUnchanged(): void
    {
        $permitRepo = $this->createStub(PermitRepositoryInterface::class);
        $verifyRepo = $this->createMock(VerificationRepositoryInterface::class);
        $verifyRepo->method('loadPending')->willReturn([]);

        $existing = new VerificationRequest('token123', new DateTimeImmutable(), ['email' => 'test@example.com']);
        $verifyRepo->method('findVerifiedByToken')->willReturn($existing);

        // Erwarte ein UPDATE in verified_pending, NICHT in pending
        $verifyRepo->expects($this->once())->method('saveVerifiedOne');
        $verifyRepo->expects($this->never())->method('deleteVerified');

        $config = $this->createStub(ConfigInterface::class);
        $config->method('getArray')->willReturnMap([
            ['permit_templates', [], ['std_7' => ['prices' => ['pkw' => 10.0]]]],
            ['vehicle_types', [], ['pkw' => ['label' => 'PKW']]],
        ]);
        $config->method('get')->willReturnMap([['max_plot_number', 9999, 9999]]);

        $calculator = new PermitFinancialCalculator($config, $this->createStub(ClockInterface::class));

        $handler = new SubmitPermitRequestHandler($config, new SystemClock(), $verifyRepo, $permitRepo, $this->createStub(EventDispatcherInterface::class), $calculator);

        $token = $handler->handle($this->getValidCommand('token123', 'test@example.com'));
        $this->assertSame('token123', $token);
    }

    #[Test]
    public function itThrowsExceptionOnDatabaseCollision(): void
    {
        $permitRepo = $this->createStub(PermitRepositoryInterface::class);
        $permitRepo->method('hasCollision')->willReturn(true); // Kollision!

        $verifyRepo = $this->createStub(VerificationRepositoryInterface::class);
        $config = $this->createStub(ConfigInterface::class);
        $config->method('get')->willReturnMap([['max_plot_number', 9999, 9999]]);

        $calculator = new PermitFinancialCalculator($config, $this->createStub(ClockInterface::class));

        $handler = new SubmitPermitRequestHandler(
            $config,
            new SystemClock(),
            $verifyRepo,
            $permitRepo,
            $this->createStub(EventDispatcherInterface::class),
            $calculator,
        );

        $this->expectException(PermitCollisionException::class);
        $handler->handle($this->getValidCommand());
    }
}
