<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Application\Services;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\System\IpResolverInterface;
use App\Modules\System\Application\Services\AuditLoggerService;
use App\Modules\System\Domain\AuditLog;
use App\Modules\System\Domain\AuditLogRepositoryInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuditLoggerService::class)]
final class AuditLoggerServiceTest extends TestCase
{
    #[Test]
    public function itLogsAdminActionsProperly(): void
    {
        $session = $this->createStub(AuthSessionInterface::class);
        $session->method('getUserId')->willReturn('usr_123');
        $session->method('getAdminUser')->willReturn('Max_Admin');

        $clock = new SystemClock();
        $repo = $this->createMock(AuditLogRepositoryInterface::class);

        // FIX: Nutzt willReturnCallback anstelle von $this->callback().
        // Das erlaubt Rector die void-Deklaration ohne PHPUnit zum Absturz zu bringen.
        $repo->expects($this->once())->method('save')->willReturnCallback(
            function (AuditLog $log): void {
                $this->assertSame('usr_123', $log->userId);
                $this->assertSame('Max_Admin', $log->username);
                $this->assertSame('TEST', $log->action);
                $this->assertSame('192.168.1.1', $log->ipAddress->value);
            },
        );

        $config = $this->createStub(ConfigInterface::class);
        $ip = $this->createStub(IpResolverInterface::class);
        $ip->method('getIp')->willReturn('192.168.1.1');

        $service = new AuditLoggerService($session, $clock, $repo, $config, $ip);
        $service->log('TEST', 'Details');
    }

    #[Test]
    public function itSkipsLoggingForStealthSuperadmins(): void
    {
        $session = $this->createStub(AuthSessionInterface::class);
        // sys_ signalisiert Superadmin
        $session->method('getUserId')->willReturn('sys_admin');

        $clock = new SystemClock();
        $repo = $this->createMock(AuditLogRepositoryInterface::class);
        $repo->expects($this->never())->method('save'); // Darf NICHT gespeichert werden

        $config = $this->createStub(ConfigInterface::class);
        $config->method('get')->willReturnMap([['stealth_superadmins', false, true]]);

        $ip = $this->createStub(IpResolverInterface::class);

        $service = new AuditLoggerService($session, $clock, $repo, $config, $ip);
        $service->log('TEST', 'Details');
    }

    #[Test]
    public function itIdentifiesHistoryUsersAndPublicUsers(): void
    {
        $session = $this->createStub(AuthSessionInterface::class);
        $session->method('getUserId')->willReturn('');
        $session->method('getHistoryEmail')->willReturn('history@test.de');

        $clock = new SystemClock();
        $repo = $this->createMock(AuditLogRepositoryInterface::class);

        $repo->expects($this->once())->method('save')->willReturnCallback(
            function (AuditLog $log): void {
                $this->assertSame('history_user', $log->userId);
                $this->assertSame('Pächter (history@test.de)', $log->username);
            },
        );

        $config = $this->createStub(ConfigInterface::class);
        $ip = $this->createStub(IpResolverInterface::class);
        $ip->method('getIp')->willReturn('127.0.0.1');

        $service = new AuditLoggerService($session, $clock, $repo, $config, $ip);
        $service->log('ACTION', 'History User');
    }
}
