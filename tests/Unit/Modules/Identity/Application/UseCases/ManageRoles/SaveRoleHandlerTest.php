<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageRoles;

use App\Modules\Identity\Application\UseCases\ManageRoles\SaveRoleCommand;
use App\Modules\Identity\Application\UseCases\ManageRoles\SaveRoleHandler;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SaveRoleHandler::class)]
final class SaveRoleHandlerTest extends TestCase
{
    #[Test]
    public function itUpdatesAnExistingRole(): void
    {
        $role = new Role('role_123', 'Old Name', ['perm.old']);
        $repo = $this->createMock(RoleRepositoryInterface::class);
        $repo->expects($this->once())->method('findById')->with('role_123')->willReturn($role);
        $repo->expects($this->once())->method('save')->willReturnCallback(
            function (Role $r): void {
                $this->assertSame('role_123', $r->id);
                $this->assertSame('New Name', $r->getName());
                $this->assertSame(['perm.new'], $r->getPermissions());
            },
        );

        $handler = new SaveRoleHandler($repo);
        $result = $handler->handle(new SaveRoleCommand('role_123', 'New Name', '', ['perm.new']));

        $this->assertTrue($result->isUpdate);
        $this->assertSame('role_123', $result->roleId);
    }

    #[Test]
    public function itCreatesANewRoleWithInheritance(): void
    {
        $inheritedRole = new Role('role_admin', 'Admin', ['*', 'permits.view']);
        $repo = $this->createMock(RoleRepositoryInterface::class);
        $repo->method('findById')->willReturnCallback(
            fn (string $id) => $id === 'role_admin' ? $inheritedRole : null,
        );

        $repo->expects($this->once())->method('save')->willReturnCallback(
            function (Role $r): void {
                $this->assertSame('Neuer Vorstand', $r->getName());
                // Berechtigungen müssen von role_admin geerbt werden (Command-Perms werden überschrieben)
                $this->assertSame(['*', 'permits.view'], $r->getPermissions());
            },
        );

        $handler = new SaveRoleHandler($repo);
        $result = $handler->handle(new SaveRoleCommand('', 'Neuer Vorstand', 'role_admin', []));

        $this->assertFalse($result->isUpdate);
        $this->assertStringStartsWith('role_', $result->roleId);
    }
}
