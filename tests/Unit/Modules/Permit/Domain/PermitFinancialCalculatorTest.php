<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Domain;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Permit\Domain\Owner;
use App\Modules\Permit\Domain\Permit;
use App\Modules\Permit\Domain\PermitFinancialCalculator;
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
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PermitFinancialCalculator::class)]
final class PermitFinancialCalculatorTest extends TestCase
{
    private function getConfigMock(): ConfigInterface
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getArray')->willReturnCallback(fn (string $key) => match ($key) {
            'permit_templates' => [
                'std_7' => ['prices' => ['pkw' => 5.0, 'lkw' => 15.0]],
                'free_tpl' => ['prices' => ['pkw' => 0.0]],
            ],
            'vehicle_types' => [
                'pkw' => ['label' => 'PKW'],
                'lkw' => ['label' => 'LKW'],
            ],
            default => []
        });

        $config->method('getInt')->willReturnCallback(fn (string $key) => match ($key) {
            'payment_due_days' => 14,
            'payment_due_days_before_validity' => 2,
            'payment_due_days_notify' => 2,
            default => 0
        });

        $config->method('getString')->willReturnCallback(fn (string $key) => match ($key) {
            'usage_pattern' => 'EFG-{{code}}-{{nachname}}-{{vorname}}',
            default => ''
        });

        return $config;
    }

    #[Test]
    public function itCalculatesBasePriceFromTemplateAndVehicleType(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $calc = new PermitFinancialCalculator($this->getConfigMock(), $clock);

        // Bekannter Typ
        $this->assertSame(15.0, $calc->calculateBasePrice(new TemplateKey('std_7'), 'lkw'));

        // Fallback auf PKW (Default), wenn Typ unbekannt ist
        $this->assertSame(5.0, $calc->calculateBasePrice(new TemplateKey('std_7'), 'unknown_type'));

        // Fallback auf 0.0, wenn Template nicht existiert
        $this->assertSame(0.0, $calc->calculateBasePrice(new TemplateKey('non_existent'), 'pkw'));
    }

    #[Test]
    #[DataProvider('dueDateProvider')]
    public function itCalculatesThePaymentDueDateCorrectly(
        string $createdStr,
        string $validFromStr,
        string $expectedDueStr,
    ): void {
        $clock = $this->createStub(ClockInterface::class);
        $calc = new PermitFinancialCalculator($this->getConfigMock(), $clock);

        $permit = new Permit(
            new PermitCode('T-1'),
            new TemplateKey('std_7'),
            new Owner('M', null, new PlotNumber(1)),
            new Vehicle('pkw', new LicensePlate('B')),
            new Validity(new DateTimeImmutable($validFromStr), new DateTimeImmutable(), new Price(10.0), 'Zweck'),
            new Status(PermitStatus::Offen),
            new DateTimeImmutable($createdStr),
        );

        $dueDate = $calc->calculatePaymentDueDate($permit);

        // Die Zeit muss immer auf 23:59:59 gesetzt werden
        $this->assertSame($expectedDueStr . ' 23:59:59', $dueDate->format('Y-m-d H:i:s'));
    }

    public static function dueDateProvider(): Iterator
    {
        // 1. Erstellt am 01.10. für 01.12.
        // Fallback (14 Tage): 15.10. | Dynamic (2 Tage vorher): 29.11.
        // MAX = 29.11.
        yield 'Validity is far in the future -> X days before validity' => [
            '2026-10-01', '2026-12-01', '2026-11-29',
        ];

        // 2. Erstellt am 01.10. für 05.10.
        // Fallback (14 Tage): 15.10. | Dynamic (2 Tage vorher): 03.10.
        // MAX = 15.10. (Das gesetzliche Minimum von 14 Tagen greift)
        yield 'Validity starts soon -> 14 days minimum apply' => [
            '2026-10-01', '2026-10-05', '2026-10-15',
        ];

        // 3. Erstellt am 01.10. für 02.10.
        // Fallback (14 Tage): 15.10. | Dynamic (2 Tage vorher): 30.09.
        // MAX = 15.10. (Das gesetzliche Minimum von 14 Tagen greift)
        yield 'Validity starts tomorrow -> 14 days minimum apply' => [
            '2026-10-01', '2026-10-02', '2026-10-15',
        ];
    }

    #[Test]
    public function itReturnsCorrectOverdueLevels(): void
    {
        // Erstellt am 1.10., Gültig ab 20.10.
        // Deadline MAX(15.10., 18.10.) -> Fällig am 18.10.
        $permit = new Permit(
            new PermitCode('T-1'),
            new TemplateKey('std_7'),
            new Owner('M', null, new PlotNumber(1)),
            new Vehicle('pkw', new LicensePlate('B')),
            new Validity(new DateTimeImmutable('2026-10-20'), new DateTimeImmutable(), new Price(10.0), 'Zweck'),
            new Status(PermitStatus::Offen),
            new DateTimeImmutable('2026-10-01'),
        );

        // Um das PHPUnit "Stub overwriting" Problem zu umgehen, instanziieren wir den Calculator
        // für jede Zeit-Prüfung frisch mit einem eigenen Clock-Stub.

        // Level 0: Nicht fällig (Heute ist der 10.10.)
        $clock0 = $this->createStub(ClockInterface::class);
        $clock0->method('now')->willReturn(new DateTimeImmutable('2026-10-10 12:00:00'));
        $calc0 = new PermitFinancialCalculator($this->getConfigMock(), $clock0);
        $this->assertSame(0, $calc0->getOverdueLevel($permit));

        // Level 1: Fällig, Mahnfrist läuft (Heute ist der 19.10., Limit für Mahnstufe 2 ist +2 Tage = 20.10.)
        $clock1 = $this->createStub(ClockInterface::class);
        $clock1->method('now')->willReturn(new DateTimeImmutable('2026-10-19 12:00:00'));
        $calc1 = new PermitFinancialCalculator($this->getConfigMock(), $clock1);
        $this->assertSame(1, $calc1->getOverdueLevel($permit));

        // Level 2: Überfällig, Mahnfrist abgelaufen (Heute ist der 21.10.)
        $clock2 = $this->createStub(ClockInterface::class);
        $clock2->method('now')->willReturn(new DateTimeImmutable('2026-10-21 12:00:00'));
        $calc2 = new PermitFinancialCalculator($this->getConfigMock(), $clock2);
        $this->assertSame(2, $calc2->getOverdueLevel($permit));

        // Paid permits never overdue
        $permit->markAsPaid();
        $this->assertSame(0, $calc2->getOverdueLevel($permit));
    }

    #[Test]
    public function itGeneratesTheUsageTextCorrectly(): void
    {
        $clock = $this->createStub(ClockInterface::class);
        $calc = new PermitFinancialCalculator($this->getConfigMock(), $clock);

        $permit = new Permit(
            new PermitCode('SYS-AB-1234'),
            new TemplateKey('std_7'),
            new Owner('Klaus Dieter Müller', null, new PlotNumber(1)),
            new Vehicle('pkw', new LicensePlate('B')),
            new Validity(new DateTimeImmutable(), new DateTimeImmutable(), new Price(10.0), 'Zweck'),
            new Status(PermitStatus::Offen),
            new DateTimeImmutable(),
        );

        // Pattern aus Mock: EFG-{{code}}-{{nachname}}-{{vorname}}
        $this->assertSame('EFG-1234-Müller-Klaus', $calc->generateUsageText($permit));
    }
}
