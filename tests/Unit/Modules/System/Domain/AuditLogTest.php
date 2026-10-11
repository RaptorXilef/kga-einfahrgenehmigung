<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Domain;

use App\Modules\System\Domain\AuditLog;
use App\SharedKernel\Domain\ValueObject\IpAddress;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditLog::class)]
final class AuditLogTest extends TestCase
{
    #[Test]
    public function itCorrectlyStoresTheValues(): void
    {
        $ip = new IpAddress('192.168.1.1');
        $now = new DateTimeImmutable();
        $log = new AuditLog('log_123', 'usr_1', 'Max', 'ACTION', 'Details', $ip, $now);

        $this->assertSame('log_123', $log->id);
        $this->assertSame('usr_1', $log->userId);
        $this->assertSame('Max', $log->username);
        $this->assertSame('ACTION', $log->action);
        $this->assertSame('Details', $log->details);
        $this->assertSame($ip, $log->ipAddress);
        $this->assertSame($now, $log->createdAt);
    }
}
