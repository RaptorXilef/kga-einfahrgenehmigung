<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Utils;

use App\SharedKernel\Infrastructure\Utils\SystemClock;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    #[Test]
    public function itReturnsCurrentTime(): void
    {
        $clock = new SystemClock();

        $now = $clock->now();
        $this->assertInstanceOf(DateTimeImmutable::class, $now);

        $str = $clock->nowAsString();
        $this->assertSame($now->format('Y-m-d H:i:s'), $str);
    }
}
