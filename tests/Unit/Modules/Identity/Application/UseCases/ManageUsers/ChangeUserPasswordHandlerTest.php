<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageUsers;

use App\Modules\Identity\Application\UseCases\ManageUsers\ChangeUserPasswordCommand;
use App\Modules\Identity\Application\UseCases\ManageUsers\ChangeUserPasswordHandler;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeUserPasswordHandler::class)]
final class ChangeUserPasswordHandlerTest extends TestCase
{
    #[Test]
    public function itChangesPasswordSuccessfullyWhenNoOldPasswordIsRequired(): void
    {
        $hash = \password_hash('old_pass', \PASSWORD_DEFAULT);
        $user = new User('usr_123', 'Max', 'role_admin', $hash, 'v0');

        $repo = $this->createMock(UserRepositoryInterface::class);
        $repo->expects($this->once())->method('findById')->with('usr_123')->willReturn($user);
        $repo->expects($this->once())->method('save')->willReturnCallback(
            function (User $u): void {
                $this->assertTrue(\password_verify('new_secret', $u->getPasswordHash()));
            },
        );

        $handler = new ChangeUserPasswordHandler($repo);
        $result = $handler->handle(new ChangeUserPasswordCommand('usr_123', 'new_secret'));

        $this->assertSame('Max', $result->username);
        $this->assertTrue(\password_verify('new_secret', $result->passwordHash));
    }

    #[Test]
    public function itChangesPasswordIfOldPasswordMatches(): void
    {
        $hash = \password_hash('correct_old', \PASSWORD_DEFAULT);
        $user = new User('usr_123', 'Max', 'role', $hash, 'v0');

        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findById')->willReturn($user);

        $handler = new ChangeUserPasswordHandler($repo);
        $handler->handle(new ChangeUserPasswordCommand('usr_123', 'new_secret', 'correct_old'));

        $this->assertTrue(\password_verify('new_secret', $user->getPasswordHash()));
    }

    #[Test]
    public function itThrowsExceptionIfOldPasswordIsIncorrect(): void
    {
        $hash = \password_hash('correct_old', \PASSWORD_DEFAULT);
        $user = new User('usr_123', 'Max', 'role', $hash, 'v0');

        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findById')->willReturn($user);

        $handler = new ChangeUserPasswordHandler($repo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Fehler: Das aktuelle Passwort ist nicht korrekt.');

        $handler->handle(new ChangeUserPasswordCommand('usr_123', 'new_secret', 'WRONG_OLD'));
    }

    #[Test]
    public function itThrowsExceptionIfUserNotFound(): void
    {
        $repo = $this->createStub(UserRepositoryInterface::class);
        $repo->method('findById')->willReturn(null);

        $handler = new ChangeUserPasswordHandler($repo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Fehler: Benutzer nicht gefunden.');

        $handler->handle(new ChangeUserPasswordCommand('unknown', 'new_secret'));
    }
}
