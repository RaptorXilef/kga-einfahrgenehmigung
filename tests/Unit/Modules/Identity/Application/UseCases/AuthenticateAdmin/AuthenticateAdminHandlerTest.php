<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\AuthenticateAdmin;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Modules\Identity\Application\UseCases\AuthenticateAdmin\AuthenticateAdminCommand;
use App\Modules\Identity\Application\UseCases\AuthenticateAdmin\AuthenticateAdminHandler;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthenticateAdminHandler::class)]
final class AuthenticateAdminHandlerTest extends TestCase
{
    private function getConfigStub(bool $disableBackdoor = false, bool $disableSuperadmin = false): ConfigInterface
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getArray')->willReturnCallback(fn (string $key): array => match ($key) {
            'superadmins' => ['super_max' => ['pass' => 'supersecret', 'label' => 'Superadmin']],
            'backdoor' => ['user' => 'dev_root', 'pass' => \password_hash('devpass', \PASSWORD_DEFAULT), 'label' => 'Dev'],
            default => []
        });
        $config->method('getBool')->willReturnCallback(fn (string $key): bool => match ($key) {
            'disable_backdoor' => $disableBackdoor,
            'disable_superadmin' => $disableSuperadmin,
            default => false
        });

        return $config;
    }

    #[Test]
    public function itThrowsExceptionIfIpIsRateLimited(): void
    {
        $rateLimiter = $this->createStub(RateLimiterInterface::class);
        $rateLimiter->method('isBlocked')->willReturn(true); // IP ist gesperrt

        $handler = new AuthenticateAdminHandler(
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(AuthSessionInterface::class),
            $rateLimiter,
            $this->createStub(ConfigInterface::class),
            $this->createStub(AuthorizationInterface::class),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Zu viele Fehlversuche. IP ist vorübergehend gesperrt.');

        $handler->handle(new AuthenticateAdminCommand('admin', 'pass', '127.0.0.1'));
    }

    #[Test]
    public function itAllowsBackdoorLoginIfEnabledAndCredentialsMatch(): void
    {
        $session = $this->createMock(AuthSessionInterface::class);
        // Expect session regeneration and authentication setup
        $session->expects($this->once())->method('regenerate');
        $session->expects($this->once())->method('rotateCsrfToken');
        $session->expects($this->once())->method('setAuthSession')->with('sys_backdoor', 'admin', 'Dev', null);

        $authService = $this->createMock(AuthorizationInterface::class);
        $authService->expects($this->once())->method('refreshSessionPermissions')->with('admin');

        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects($this->once())->method('clearAttempts')->with('127.0.0.1');

        $handler = new AuthenticateAdminHandler(
            $this->createStub(UserRepositoryInterface::class),
            $session,
            $rateLimiter,
            $this->getConfigStub(), // Backdoor is enabled
            $authService,
        );

        $handler->handle(new AuthenticateAdminCommand('dev_root', 'devpass', '127.0.0.1'));
    }

    #[Test]
    public function itAllowsSuperadminLoginWithPlaintextPasswordIfEnabled(): void
    {
        $session = $this->createMock(AuthSessionInterface::class);
        $session->expects($this->once())->method('setAuthSession')->with('sys_super_max', 'admin', 'Superadmin', null);

        $handler = new AuthenticateAdminHandler(
            $this->createStub(UserRepositoryInterface::class),
            $session,
            $this->createStub(RateLimiterInterface::class),
            $this->getConfigStub(), // Superadmin is enabled
            $this->createStub(AuthorizationInterface::class),
        );

        $handler->handle(new AuthenticateAdminCommand('super_max', 'supersecret', '127.0.0.1'));
    }

    #[Test]
    public function itAllowsRegularDatabaseUserLoginAndSetsHash(): void
    {
        $hash = \password_hash('correct_pass', \PASSWORD_DEFAULT);
        $user = new User('usr_123', 'db_user', 'role_custom', $hash, 'v0');

        $repo = $this->createStub(UserRepositoryInterface::class);
        // D-FIX: Stubs dürfen keine Input-Validierung (->with) durchführen!
        $repo->method('findByUsername')->willReturn($user);

        $session = $this->createMock(AuthSessionInterface::class);
        $session->expects($this->once())->method('setAuthSession')->with('usr_123', 'role_custom', 'db_user', $hash);

        $handler = new AuthenticateAdminHandler(
            $repo,
            $session,
            $this->createStub(RateLimiterInterface::class),
            $this->getConfigStub(),
            $this->createStub(AuthorizationInterface::class),
        );

        $handler->handle(new AuthenticateAdminCommand('db_user', 'correct_pass', '127.0.0.1'));
    }

    #[Test]
    public function itRecordsStrikeAndThrowsExceptionOnInvalidCredentials(): void
    {
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findByUsername')->willReturn(null); // User not found

        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->expects($this->once())->method('recordFailedAttempt')->with('192.168.1.1');

        $handler = new AuthenticateAdminHandler(
            $repo,
            $this->createStub(AuthSessionInterface::class),
            $rateLimiter,
            $this->getConfigStub(),
            $this->createStub(AuthorizationInterface::class),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Benutzername oder Passwort ist falsch.');

        $handler->handle(new AuthenticateAdminCommand('unknown_user', 'wrong_pass', '192.168.1.1'));
    }
}
