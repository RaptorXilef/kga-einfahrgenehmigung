<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Application\UseCases\ManageUsers\ChangeUserRoleCommand;
use App\Modules\Identity\Application\UseCases\ManageUsers\ChangeUserRoleHandler;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeUserRoleHandler::class)]
final class ChangeUserRoleHandlerTest extends TestCase
{
    #[Test]
    public function itChangesRoleAndReturnsDetailedResultDto(): void
    {
        $user = new User('usr_123', 'Max', 'role_old', 'hash', 'v0');

        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $userRepo->expects($this->once())->method('findById')->with('usr_123')->willReturn($user);
        $userRepo->expects($this->once())->method('save')->willReturnCallback(
            function (User $u): void {
                $this->assertSame('role_new', $u->getRoleId());
            },
        );

        $roleRepo = $this->createStub(RoleRepositoryInterface::class);
        $roleRepo->method('findById')->willReturnCallback(fn (string $id): ?Role => match ($id) {
            'role_old' => new Role('role_old', 'Alte Rolle', []),
            'role_new' => new Role('role_new', 'Neue Rolle', []),
            default => null
        });

        $handler = new ChangeUserRoleHandler($userRepo, $roleRepo);
        $result = $handler->handle(new ChangeUserRoleCommand('usr_123', 'role_new'));

        $this->assertSame('Max', $result->username);
        $this->assertSame('Alte Rolle', $result->oldRoleName);
        $this->assertSame('Neue Rolle', $result->newRoleName);
    }

    #[Test]
    public function itThrowsExceptionIfUserNotFound(): void
    {
        $userRepo = $this->createStub(UserRepositoryInterface::class);
        $userRepo->method('findById')->willReturn(null);

        $handler = new ChangeUserRoleHandler($userRepo, $this->createStub(RoleRepositoryInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Fehler: Benutzer nicht gefunden.');

        $handler->handle(new ChangeUserRoleCommand('unknown', 'role_new'));
    }
}
