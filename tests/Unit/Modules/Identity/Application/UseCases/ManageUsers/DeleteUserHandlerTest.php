<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Application\UseCases\ManageUsers\DeleteUserCommand;
use App\Modules\Identity\Application\UseCases\ManageUsers\DeleteUserHandler;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteUserHandler::class)]
final class DeleteUserHandlerTest extends TestCase
{
    #[Test]
    public function itDeletesUserAndReturnsUsername(): void
    {
        $user = new User('usr_target', 'Target User', 'role', 'hash', 'v0');
        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())->method('findById')->with('usr_target')->willReturn($user);
        $repo->expects($this->once())->method('delete')->with('usr_target');

        $handler = new DeleteUserHandler($repo);
        $deletedName = $handler->handle(new DeleteUserCommand('usr_target', 'usr_admin'));

        $this->assertSame('Target User', $deletedName);
    }

    #[Test]
    public function itThrowsExceptionOnSelfDelete(): void
    {
        $handler = new DeleteUserHandler($this->createStub(UserRepositoryInterface::class));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Fehler: Selbstausschluss nicht möglich.');

        $handler->handle(new DeleteUserCommand('usr_same', 'usr_same'));
    }

    #[Test]
    public function itThrowsExceptionIfUserNotFound(): void
    {
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new DeleteUserHandler($repo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Fehler: Benutzer nicht gefunden.');

        $handler->handle(new DeleteUserCommand('usr_unknown', 'usr_admin'));
    }
}
