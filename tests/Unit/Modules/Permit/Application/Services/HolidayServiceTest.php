<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Permit\Application\Services;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Modules\Permit\Application\Services\HolidayService;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use DateTimeImmutable;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HolidayService::class)]
final class HolidayServiceTest extends TestCase
{
    private function getConfigStub(): ConfigInterface
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBool')->willReturnCallback(fn (string $key): bool => match ($key) {
            'use_auto_holidays' => true,
            'holiday_service_use_full_list' => false,
            default => false,
        });
        $config->method('getString')->willReturnCallback(fn (string $key): string => match ($key) {
            'holiday_check' => 'Berlin',
            default => ''
        });
        $config->method('getArray')->willReturnCallback(fn (string $key): array => match ($key) {
            'custom_holidays' => ['2026-12-24'],
            'default_opening_hours' => [
                'mon' => [['07:00', '20:00']],
                'tue' => [['07:00', '20:00']],
                'sun' => [], // Sonntag = Ruhetag
            ],
            'seasons' => [],
            default => []
        });

        return $config;
    }

    #[Test]
    #[DataProvider('berlinHolidayProvider')]
    public function itCalculatesHolidaysCorrectlyIncludingEasterGauss(string $dateStr, bool $isRestricted): void
    {
        $service = new HolidayService($this->getConfigStub(), $this->createStub(JsonHelperInterface::class), new SystemClock());
        $date = new DateTimeImmutable($dateStr);

        $this->assertSame($isRestricted, $service->isRestrictedDay($date));
    }

    public static function berlinHolidayProvider(): Iterator
    {
        // Feste Feiertage Berlin
        yield 'Neujahr' => ['2026-01-01', true];
        yield 'Frauentag (Berlin)' => ['2026-03-08', true];
        yield 'Tag der Deutschen Einheit' => ['2026-10-03', true];

        // Bewegliche Feiertage (Ostern 2026 ist am 05.04.2026)
        yield 'Karfreitag 2026' => ['2026-04-03', true];
        yield 'Ostermontag 2026' => ['2026-04-06', true];
        yield 'Christi Himmelfahrt 2026' => ['2026-05-14', true];
        yield 'Pfingstmontag 2026' => ['2026-05-25', true];

        // Custom Holiday aus Config
        yield 'Heiligabend (Custom)' => ['2026-12-24', true];

        // Normale Werktage
        yield 'Normaler Montag' => ['2026-10-05', false];
        yield 'Normaler Dienstag' => ['2026-10-06', false];

        // Sonntag (Laut Config [] = gesperrt)
        yield 'Sonntag' => ['2026-10-04', true];
    }

    #[Test]
    public function itReturnsCorrectTimeAllowedNowState(): void
    {
        $clock = $this->createStub(SystemClock::class);
        $service = new HolidayService($this->getConfigStub(), $this->createStub(JsonHelperInterface::class), $clock);

        // Fall 1: Montag, 12:00 Uhr (Erlaubt: 07:00 - 20:00)
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-05 12:00:00'));
        $this->assertTrue($service->isTimeAllowedNow());

        // Fall 2: Montag, 21:00 Uhr (Außerhalb)
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-05 21:00:00'));
        $this->assertFalse($service->isTimeAllowedNow());

        // Fall 3: Feiertag (Karfreitag), 12:00 Uhr
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-04-03 12:00:00'));
        $this->assertFalse($service->isTimeAllowedNow());
    }

    #[Test]
    public function itFindsNextAvailableSlotProperly(): void
    {
        $clock = $this->createStub(SystemClock::class);
        $service = new HolidayService($this->getConfigStub(), $this->createStub(JsonHelperInterface::class), $clock);

        // Es ist Sonntag, 12:00 Uhr. Nächster Slot ist Montag 07:00 Uhr
        $now = new DateTimeImmutable('2026-10-04 12:00:00');
        $next = $service->getNextAvailableSlot($now);

        $this->assertNotNull($next);
        $this->assertSame('2026-10-05 07:00:00', $next->format('Y-m-d H:i:s'));
    }
}
