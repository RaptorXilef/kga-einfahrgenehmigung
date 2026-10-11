<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Application\UseCases\ManageUsers\CreateUserCommand;
use App\Modules\Identity\Application\UseCases\ManageUsers\CreateUserHandler;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\RoleRepositoryInterface;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\UserUniquenessChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateUserHandler::class)]
final class CreateUserHandlerTest extends TestCase
{
    #[Test]
    public function itChecksUniquenessSavesUserAndReturnsDto(): void
    {
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())->method('save')->willReturnCallback(
            function (User $user): void {
                $this->assertStringStartsWith('usr_', $user->id);
                $this->assertSame('new_max', $user->getUsername());
                $this->assertSame('role_123', $user->getRoleId());
                $this->assertTrue(\password_verify('secret123', $user->getPasswordHash()));
            },
        );

        $roleRepo = $this->createStub(RoleRepositoryInterface::class);
        $roleRepo->method('findById')->with('role_123')->willReturn(new Role('role_123', 'Admin Rolle', []));

        $uniquenessChecker = $this->createMock(UserUniquenessChecker::class);
        $uniquenessChecker->expects($this->once())->method('check')->with('new_max');

        $handler = new CreateUserHandler($repo, $roleRepo, $uniquenessChecker);
        $result = $handler->handle(new CreateUserCommand('new_max', 'secret123', 'role_123'));

        $this->assertSame('Admin Rolle', $result->roleName);
        $this->assertStringStartsWith('usr_', $result->userId);
    }
}
