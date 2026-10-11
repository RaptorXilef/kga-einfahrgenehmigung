<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Infrastructure\Security;

use App\Contracts\Utils\ClockInterface;
use App\Modules\Identity\Domain\LoginAttempt;
use App\Modules\Identity\Domain\LoginAttemptRepositoryInterface;
use App\Modules\Identity\Infrastructure\Security\RateLimiter;
use App\SharedKernel\Domain\ValueObject\IpAddress;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RateLimiter::class)]
final class RateLimiterTest extends TestCase
{
    #[Test]
    public function itReturnsTrueIfAttemptsExceedMaxAndLockoutHasNotPassed(): void
    {
        $now = new DateTimeImmutable('2026-10-15 12:10:00'); // 10 Min vergangen
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        // 5 Versuche vor 10 Minuten
        $attempt = new LoginAttempt(new IpAddress('10.0.0.1'), 5, new DateTimeImmutable('2026-10-15 12:00:00'));

        $repo = $this->createStub(LoginAttemptRepositoryInterface::class);
        $repo->method('findByIp')->willReturn($attempt);

        $limiter = new RateLimiter($clock, $repo);
        $this->assertTrue($limiter->isBlocked('10.0.0.1'));
    }

    #[Test]
    public function itReturnsFalseAndClearsIfLockoutTimeHasPassed(): void
    {
        $now = new DateTimeImmutable('2026-10-15 12:20:00'); // 20 Min vergangen
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $attempt = new LoginAttempt(new IpAddress('10.0.0.1'), 5, new DateTimeImmutable('2026-10-15 12:00:00'));

        $repo = $this->createMock(LoginAttemptRepositoryInterface::class);
        $repo->method('findByIp')->willReturn($attempt);
        // Da die Zeit abgelaufen ist, muss der Eintrag gelöscht werden
        $repo->expects($this->once())->method('deleteByIp')->with('10.0.0.1');

        $limiter = new RateLimiter($clock, $repo);
        $this->assertFalse($limiter->isBlocked('10.0.0.1'));
    }

    #[Test]
    public function itRecordsFailedAttemptsCorrectly(): void
    {
        $now = new DateTimeImmutable('2026-10-15 12:00:00');
        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn($now);

        $repo = $this->createMock(LoginAttemptRepositoryInterface::class);
        $repo->method('findByIp')->willReturn(null); // Noch kein Eintrag

        $repo->expects($this->once())->method('deleteOlderThan')->with(15);
        $repo->expects($this->once())->method('save')->willReturnCallback(
            function (LoginAttempt $attempt) use ($now): void {
                $this->assertSame('10.0.0.5', $attempt->ipAddress->value);
                $this->assertSame(1, $attempt->attempts);
                $this->assertSame($now, $attempt->lastAttempt);
            },
        );

        $limiter = new RateLimiter($clock, $repo);
        $limiter->recordFailedAttempt('10.0.0.5');
    }
}
