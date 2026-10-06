<?php

declare(strict_types=1);

use App\Modules\Identity\Application\UseCases\ManageRoles\RenameRoleCommand;
use App\Modules\Identity\Application\UseCases\ManageRoles\RenameRoleHandler;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;

covers(RenameRoleHandler::class);

test('it renames an existing role and saves it to the repository', function (): void {
    $role = new Role('role_support', 'Support', ['permits.view']);

    /** @var RoleRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject $repository */
    $repository = $this->createMock(RoleRepositoryInterface::class);

    $repository->expects($this->once())
        ->method('findById')
        ->with('role_support')
        ->willReturn($role);

    $repository->expects($this->once())
        ->method('save')
        ->with($role);

    $handler = new RenameRoleHandler($repository);

    // Der Handler liefert laut Architektur den alten Namen für das Audit-Log zurück
    $oldName = $handler->handle(new RenameRoleCommand('role_support', 'Kundenservice'));

    expect($oldName)->toBe('Support')
        ->and($role->getName())->toBe('Kundenservice');
});

test('it throws an exception if the role to rename does not exist', function (): void {
    /** @var RoleRepositoryInterface&\PHPUnit\Framework\MockObject\Stub $repository */
    $repository = $this->createStub(RoleRepositoryInterface::class);
    $repository->method('findById')->willReturn(null);

    $handler = new RenameRoleHandler($repository);

    $handler->handle(new RenameRoleCommand('role_ghost', 'New Name'));
})->throws(\DomainException::class, 'Rolle nicht gefunden.');
