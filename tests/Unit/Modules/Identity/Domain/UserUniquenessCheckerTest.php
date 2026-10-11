<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\Modules\Identity\Domain\UserUniquenessChecker;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserUniquenessChecker::class)]
final class UserUniquenessCheckerTest extends TestCase
{
    #[Test]
    public function itPassesIfUsernameIsAvailable(): void
    {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn(null);

        $checker = new UserUniquenessChecker($repository);
        $checker->check('new_user');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function itPassesIfUsernameBelongsToTheSameUser(): void
    {
        $user = new User('usr_123', 'existing_user', 'role', 'hash', 'v0');
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn($user);

        $checker = new UserUniquenessChecker($repository);

        // Prüfung, ob der Name geändert wurde, aber immer noch derselbe User ist
        $checker->check('existing_user', 'usr_123');

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function itThrowsExceptionIfUsernameIsAlreadyTakenByAnotherUser(): void
    {
        $user = new User('usr_123', 'taken_user', 'role', 'hash', 'v0');
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('findByUsername')->willReturn($user);

        $checker = new UserUniquenessChecker($repository);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Fehler: Ein Benutzer mit dem Namen 'taken_user' existiert bereits.");

        $checker->check('taken_user', 'usr_999'); // Wir wollen usr_999 zu taken_user umbenennen
    }
}
