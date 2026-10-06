<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases;

use App\Modules\Identity\Application\UseCases\ManageRoles\RenameRoleCommand;
use App\Modules\Identity\Application\UseCases\ManageRoles\RenameRoleHandler;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RenameRoleHandler::class)]
final class RenameRoleHandlerTest extends TestCase
{
    #[Test]
    public function itRenamesAnExistingRoleAndSavesItToTheRepository(): void
    {
        $role = new Role('role_support', 'Support', ['permits.view']);

        $repository = $this->createMock(RoleRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('findById')
            ->with('role_support')
            ->willReturn($role);

        $repository->expects($this->once())
            ->method('save')
            ->with($role);

        $handler = new RenameRoleHandler($repository);
        $oldName = $handler->handle(new RenameRoleCommand('role_support', 'Kundenservice'));

        $this->assertSame('Support', $oldName);
        $this->assertSame('Kundenservice', $role->getName());
    }

    #[Test]
    public function itThrowsAnExceptionIfTheRoleToRenameDoesNotExist(): void
    {
        $repository = $this->createStub(RoleRepositoryInterface::class);
        $repository->method('findById')->willReturn(null);

        $handler = new RenameRoleHandler($repository);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Rolle nicht gefunden.');

        $handler->handle(new RenameRoleCommand('role_ghost', 'New Name'));
    }
}
