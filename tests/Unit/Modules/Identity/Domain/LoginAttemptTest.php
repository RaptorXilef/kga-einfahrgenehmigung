<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\LoginAttempt;
use App\SharedKernel\Domain\ValueObject\IpAddress;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LoginAttempt::class)]
final class LoginAttemptTest extends TestCase
{
    #[Test]
    public function itCorrectlyStoresTheValues(): void
    {
        $ip = new IpAddress('127.0.0.1');
        $now = new DateTimeImmutable();
        $attempt = new LoginAttempt($ip, 3, $now);

        $this->assertSame($ip, $attempt->ipAddress);
        $this->assertSame(3, $attempt->attempts);
        $this->assertSame($now, $attempt->lastAttempt);
    }
}
