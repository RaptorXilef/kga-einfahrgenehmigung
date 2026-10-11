<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Domain;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\PermitFactory;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\PermitStatus;
use App\Modules\Permit\Domain\Vehicle;
use App\SharedKernel\Domain\ValueObject\LicensePlate;
use App\SharedKernel\Domain\ValueObject\PlotNumber;
use App\SharedKernel\Domain\ValueObject\Price;
use App\SharedKernel\Domain\ValueObject\TemplateKey;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermitFactory::class)]
final class PermitFactoryTest extends TestCase
{
    #[Test]
    public function itCalculatesValidityEndDatesAndResolvesPurpose(): void
    {
        $repository = $this->createStub(PermitRepositoryInterface::class);
        $repository->method('isCodeUnique')->willReturn(true);

        $config = $this->createStub(ConfigInterface::class);
        $config->method('getArray')->willReturnMap([
            ['permit_templates', [], [
                'std_7' => ['days' => 7],
                'custom' => ['days' => 'custom'],
                'invalid' => ['days' => 'bla'], // Fallback 1 day
            ]],
            ['purposes', [], [
                'bau' => 'Baumaßnahme',
            ]],
        ]);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-11'));

        $factory = new PermitFactory($repository, $config, $clock);

        $owner = new Owner('Max Mustermann', null, new PlotNumber(42));
        $vehicle = new Vehicle('pkw', new LicensePlate('B-XX 123'));
        $start = new DateTimeImmutable('2026-10-01');
        $customEnd = new DateTimeImmutable('2026-10-31');

        // Test 1: Standard 7 Tage (fügt 6 Tage hinzu -> 07.10.)
        $permit1 = $factory->createNew(
            new TemplateKey('std_7'),
            clone $owner,
            clone $vehicle,
            $start,
            null,
            new Price(10.0),
            'bau',
            PermitStatus::Offen,
            null,
            [],
        );
        $this->assertSame('2026-10-07', $permit1->getValidUntil()->format('Y-m-d'));
        $this->assertSame('Baumaßnahme', $permit1->getPurpose());

        // Test 2: Custom Tage (Nutzt customEndDate) und unbekannter Purpose-Fallback
        $permit2 = $factory->createNew(
            new TemplateKey('custom'),
            clone $owner,
            clone $vehicle,
            $start,
            $customEnd,
            new Price(10.0),
            'Unbekannt',
            PermitStatus::Offen,
            null,
            [],
        );
        $this->assertSame('2026-10-31', $permit2->getValidUntil()->format('Y-m-d'));
        // Strip tags fallback
        $this->assertSame('Unbekannt', $permit2->getPurpose());

        // Test 3: Invalid Days (Fallback auf 1 Tag -> Start = Ende) und leerer Purpose (Fallback auf Privat)
        $permit3 = $factory->createNew(
            new TemplateKey('invalid'),
            clone $owner,
            clone $vehicle,
            $start,
            null,
            new Price(10.0),
            '',
            PermitStatus::Offen,
            null,
            [],
        );
        $this->assertSame('2026-10-01', $permit3->getValidUntil()->format('Y-m-d'));
        $this->assertSame('Privat', $permit3->getPurpose());
    }

    #[Test]
    public function itGeneratesUniqueLongCodesAndRetriesOnCollisions(): void
    {
        $repository = $this->createMock(PermitRepositoryInterface::class);

        // KILLT MUTANTE (while loop uniqueness): Simuliere eine Kollision beim 1. Versuch, Erfolg beim 2. Versuch
        $repository->expects($this->exactly(2))
            ->method('isCodeUnique')
            ->willReturnOnConsecutiveCalls(false, true);

        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBool')->willReturnMap([['use_long_permit_code', false, true]]);
        $config->method('getString')->willReturnMap([['prefix', 'ML', 'ZM']]);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable());

        $factory = new PermitFactory($repository, $config, $clock);
        $permit = $factory->createNew(
            new TemplateKey('std_7'),
            new Owner('M', null, new PlotNumber(42)),
            new Vehicle('pkw', new LicensePlate('B XX 123')),
            new DateTimeImmutable(),
            null,
            new Price(0.0),
            '',
            PermitStatus::Offen,
            null,
            [],
        );

        $this->assertStringStartsWith('ZM-0042-B-XX-123-', $permit->code->value);
    }
}
