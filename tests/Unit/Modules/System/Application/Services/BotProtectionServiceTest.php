<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Application\Services;

use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\System\Application\Services\BotProtectionService;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BotProtectionService::class)]
final class BotProtectionServiceTest extends TestCase
{
    #[Test]
    public function itThrowsIfRateLimitIsExceeded(): void
    {
        $limiter = $this->createStub(RateLimiterInterface::class);
        $limiter->method('isBlocked')->willReturn(true);
        $clock = $this->createStub(ClockInterface::class);

        $service = new BotProtectionService($limiter, $clock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Zu viele Anträge in kurzer Zeit.');

        $service->checkRateLimit('127.0.0.1');
    }

    #[Test]
    public function itDelegatesRecordStrike(): void
    {
        $limiter = $this->createMock(RateLimiterInterface::class);
        $limiter->expects($this->once())->method('recordFailedAttempt')->with('127.0.0.1');
        $clock = $this->createStub(ClockInterface::class);

        $service = new BotProtectionService($limiter, $clock);
        $service->recordStrike('127.0.0.1');
    }

    #[Test]
    public function itThrowsIfTimeCheckFailsBecauseOfZeroStart(): void
    {
        $limiter = $this->createStub(RateLimiterInterface::class);
        $clock = $this->createStub(ClockInterface::class);

        $service = new BotProtectionService($limiter, $clock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Sicherheits-Token abgelaufen');

        // Formular-Start war 0 (z.B. Session weg)
        $service->verifyTimeCheck(0, 3);
    }

    #[Test]
    public function itThrowsIfTimeCheckFailsBecauseTooFast(): void
    {
        $limiter = $this->createStub(RateLimiterInterface::class);
        $clock = $this->createStub(ClockInterface::class);
        // Sagen wir, jetzt ist Sekunde 100
        $clock->method('now')->willReturn((new DateTimeImmutable())->setTimestamp(100));

        $service = new BotProtectionService($limiter, $clock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Das Formular wurde zu schnell ausgefüllt');

        // Formular-Start war 98. Diff = 2 Sekunden. Min = 3. -> Knallt!
        $service->verifyTimeCheck(98, 3);
    }

    #[Test]
    public function itPassesTimeCheckIfSlowEnough(): void
    {
        $limiter = $this->createStub(RateLimiterInterface::class);
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn((new DateTimeImmutable())->setTimestamp(100));

        $service = new BotProtectionService($limiter, $clock);

        // Formular-Start war 97. Diff = 3 Sekunden. Min = 3. -> Passt! (Tötet < vs <= Mutant)
        $service->verifyTimeCheck(97, 3);
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function itThrowsIfHoneypotIsFilled(): void
    {
        $limiter = $this->createStub(RateLimiterInterface::class);
        $clock = $this->createStub(ClockInterface::class);

        $service = new BotProtectionService($limiter, $clock);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Spam-Schutz aktiviert');

        $service->verifyHoneypot('Ich bin ein Bot');
    }
}
