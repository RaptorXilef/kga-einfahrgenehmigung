<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\Services;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthService::class)]
final class AuthServiceTest extends TestCase
{
    private function getConfigStub(): ConfigInterface
    {
        $config = $this->createStub(ConfigInterface::class);

        $config->method('getArray')->willReturnCallback(fn (string $key): array => match ($key) {
            'superadmins' => ['max' => ['label' => 'Superadmin']],
            'backdoor' => ['user' => 'dev', 'label' => 'Dev', 'pass' => 'xxx'],
            'permissions' => ['valid.perm' => 'Valid'],
            'structure' => ['stats' => ['key' => 'stats.view']],
            default => []
        });

        $config->method('getBool')->willReturnCallback(fn (string $key): bool => match ($key) {
            'disable_backdoor' => false,
            'disable_superadmin' => false,
            'stealth_superadmins' => true,
            default => false
        });

        return $config;
    }

    #[Test]
    public function itCorrectlyEvaluatesIsLoggedInForDifferentTiers(): void
    {
        $config = $this->getConfigStub();

        $session = $this->createMock(AuthSessionInterface::class);
        $repo = $this->createStub(UserRepositoryInterface::class);
        $roleRepo = $this->createStub(RoleRepositoryInterface::class);

        $auth = new AuthService($config, $roleRepo, $session, $repo);

        // Fall 1: Niemand eingeloggt
        $session->method('getUserId')->willReturn('');
        $session->method('getAdminUser')->willReturn('');
        $this->assertFalse($auth->isLoggedIn());

        // Fall 2: Superadmin aus der config
        $session = $this->createStub(AuthSessionInterface::class);
        $session->method('getUserId')->willReturn('sys_max');
        $session->method('getAdminUser')->willReturn('Superadmin');
        $auth2 = new AuthService($config, $roleRepo, $session, $repo);
        $this->assertTrue($auth2->isLoggedIn());

        // Fall 3: Regulärer DB User mit Hash-Sync
        $hash = \password_hash('test', \PASSWORD_DEFAULT);
        $user = new User('usr_1', 'Max', 'role_admin', $hash, 'v0');
        $repo->method('findById')->willReturn($user);

        $session3 = $this->createMock(AuthSessionInterface::class);
        $session3->method('getUserId')->willReturn('usr_1');
        $session3->method('getAuthHash')->willReturn($hash);
        // Erwartet das Session Update
        $session3->expects($this->once())->method('setAuthSession');

        $auth3 = new AuthService($config, $roleRepo, $session3, $repo);
        $this->assertTrue($auth3->isLoggedIn());
    }

    #[Test]
    public function itLogsOutIfDbSyncFailsOrUserIsDeleted(): void
    {
        $config = $this->getConfigStub();
        $session = $this->createMock(AuthSessionInterface::class);
        $repo = $this->createStub(UserRepositoryInterface::class);
        $roleRepo = $this->createStub(RoleRepositoryInterface::class);

        $session->method('getUserId')->willReturn('usr_deleted');
        $repo->method('findById')->willReturn(null); // Gelöscht!

        // Erwarte Logout-Call
        $session->expects($this->once())->method('destroy');

        $auth = new AuthService($config, $roleRepo, $session, $repo);
        $this->assertFalse($auth->isLoggedIn());
    }

    #[Test]
    public function itEvaluatesPermissionsAndWildcards(): void
    {
        $config = $this->getConfigStub();
        $repo = $this->createStub(UserRepositoryInterface::class);
        $roleRepo = $this->createStub(RoleRepositoryInterface::class);

        // Sys-Admin darf immer alles
        $sessionSys = $this->createStub(AuthSessionInterface::class);
        $sessionSys->method('getUserId')->willReturn('sys_admin');
        $auth1 = new AuthService($config, $roleRepo, $sessionSys, $repo);
        $this->assertTrue($auth1->hasPermission('stats.view'));

        // Normaler User mit Rechten in der Session
        $hash = \password_hash('test', \PASSWORD_DEFAULT);
        $user = new User('usr_1', 'Max', 'role_custom', $hash, 'v0');
        $repo->method('findById')->willReturn($user);

        $sessionNorm = $this->createStub(AuthSessionInterface::class);
        $sessionNorm->method('getUserId')->willReturn('usr_1');
        $sessionNorm->method('getAuthHash')->willReturn($hash);
        $sessionNorm->method('getAdminGroup')->willReturn('role_custom');
        // Liefert Live-Rechte aus der DB, wenn geladen
        $roleRepo->method('loadAll')->willReturn([
            'role_custom' => new Role('role_custom', 'Custom', ['stats.view']),
        ]);
        $sessionNorm->method('getPermissions')->willReturn(['stats.view' => true]);

        $auth2 = new AuthService($config, $roleRepo, $sessionNorm, $repo);
        $this->assertTrue($auth2->hasPermission('stats.view'));
        $this->assertFalse($auth2->hasPermission('system.manage'));
    }

    #[Test]
    public function itRefreshesSessionPermissionsCorrectly(): void
    {
        $config = $this->getConfigStub();
        $repo = $this->createStub(UserRepositoryInterface::class);

        $roleRepo = $this->createStub(RoleRepositoryInterface::class);
        $roleRepo->method('loadAll')->willReturn([
            'role_admin' => new Role('role_admin', 'Administrator', ['*']),
        ]);

        $session = $this->createMock(AuthSessionInterface::class);
        // Erwarte Kompilierung und Speichern in Session
        $session->expects($this->once())->method('setPermissions')->with(['stats.view' => true]);
        $session->expects($this->once())->method('setAdminRoleName')->with('Administrator');

        $auth = new AuthService($config, $roleRepo, $session, $repo);
        $auth->refreshSessionPermissions('role_admin');
    }

    #[Test]
    public function itBootstrapsDefaultIdentityDataAndCleansOrphans(): void
    {
        $config = $this->getConfigStub();
        $roleRepo = $this->createMock(RoleRepositoryInterface::class);
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $session = $this->createStub(AuthSessionInterface::class);

        // Gebe eine Rolle mit einem verwaisten (orphaned) Recht zurück
        $role = new Role('role_1', 'R1', ['valid.perm', 'old.deleted.perm']);
        $roleRepo->method('loadAll')->willReturn(['role_1' => $role]);
        $userRepo->method('findById')->willThrowException(new Exception('Not found'));

        // Erwarte, dass die Rolle GEREINIGT gespeichert wird (1 Save)
        // Die 4 Defaults werden übersprungen, da das Repository nicht leer ist!
        $roleRepo->expects($this->once())->method('save');
        // 1 Default Admin User wird erstellt
        $userRepo->expects($this->once())->method('save');

        $auth = new AuthService($config, $roleRepo, $session, $userRepo);

        // Output-Buffering fängt den error_log STDOUT Leak bei manchen CLI Konfigurationen zuverlässig ab!
        \ob_start();
        $auth->bootstrapDefaultIdentityData();
        \ob_end_clean();

        $this->assertSame(['valid.perm'], $role->getPermissions());
    }
}
