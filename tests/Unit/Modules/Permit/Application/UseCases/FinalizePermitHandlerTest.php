<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Application\UseCases;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Application\UseCases\FinalizePermit\FinalizePermitCommand;
use App\Modules\Permit\Application\UseCases\FinalizePermit\FinalizePermitHandler;
use App\Modules\Permit\Domain\PermitFactory;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\Modules\Permit\Domain\VerificationRequest;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(FinalizePermitHandler::class)]
final class FinalizePermitHandlerTest extends TestCase
{
    #[Test]
    public function itHydratesPermitDispatchesEventAndDeletesVerifiedRequest(): void
    {
        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('executeWithLock')->willReturnCallback(fn (string $name, callable $op) => $op());

        $reqData = [
            'datum_von' => '2026-10-15',
            'datum_bis' => '2026-10-20',
            'email' => 'test@example.com',
            'firma' => 'Baufirma',
            'template_key' => 'std_7',
            'name' => 'Max',
            'parzelle' => '42',
            'typ' => 'pkw',
            'kennzeichen' => 'B-XX 123',
            'preis' => 10.0,
            'zweck' => 'Bau',
        ];
        $req = new VerificationRequest('token123', new DateTimeImmutable(), $reqData);

        $verifyRepo = $this->createMock(VerificationRepositoryInterface::class);
        $verifyRepo->method('findVerifiedByToken')->with('token123')->willReturn($req);
        $verifyRepo->expects($this->once())->method('deleteVerified')->with('token123');

        $permitRepo = $this->createMock(PermitRepositoryInterface::class);
        $permitRepo->method('isCodeUnique')->willReturn(true);
        $permitRepo->expects($this->once())->method('save');

        // Nutzt die echte PermitFactory anstatt eines Mock, da sie final ist
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getArray')->willReturnMap([['permit_templates', [], ['std_7' => ['days' => 7]]]]);
        $config->method('getBool')->willReturnMap([['use_long_permit_code', false, false]]);

        $permitFactory = new PermitFactory($permitRepo, $config, $this->createStub(ClockInterface::class));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch'); // PermitCreatedEvent

        $handler = new FinalizePermitHandler($lockManager, $verifyRepo, $permitRepo, $permitFactory, $eventDispatcher);
        $code = $handler->handle(new FinalizePermitCommand('token123', PermitStatus::Offen));

        $this->assertIsString($code);
        $this->assertSame(8, \strlen($code)); // Der generierte Random Code hat 8 Stellen
    }

    #[Test]
    public function itThrowsExceptionIfSessionExpired(): void
    {
        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('executeWithLock')->willReturnCallback(fn (string $name, callable $op) => $op());

        $verifyRepo = $this->createStub(VerificationRepositoryInterface::class);
        $verifyRepo->method('findVerifiedByToken')->willReturn(null);

        $permitFactory = new PermitFactory(
            $this->createStub(PermitRepositoryInterface::class),
            $this->createStub(ConfigInterface::class),
            $this->createStub(ClockInterface::class),
        );

        $handler = new FinalizePermitHandler(
            $lockManager,
            $verifyRepo,
            $this->createStub(PermitRepositoryInterface::class),
            $permitFactory,
            $this->createStub(EventDispatcherInterface::class),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Antragssitzung abgelaufen oder bereits abgeschlossen.');

        $handler->handle(new FinalizePermitCommand('invalid', PermitStatus::Offen));
    }
}
