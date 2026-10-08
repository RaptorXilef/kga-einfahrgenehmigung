<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\User;
use DomainException;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    #[Test]
    public function itCreatesAUserAndAllowsRetrievingAndUpdatingProperties(): void
    {
        $user = new User('usr_123', 'max_m', 'role_admin', 'hash123', 'v1.0.0');

        $this->assertSame('usr_123', $user->id);
        $this->assertSame('max_m', $user->getUsername());
        $this->assertSame('role_admin', $user->getRoleId());
        $this->assertSame('hash123', $user->getPasswordHash());
        $this->assertSame('v1.0.0', $user->getLastSeenChangelog());

        $user->markChangelogAsRead('v2.1.0');
        $this->assertSame('v2.1.0', $user->getLastSeenChangelog());
    }

    #[Test]
    public function itVerifiesAndChangesPasswordCorrectly(): void
    {
        $hash = \password_hash('OldP4ssw0rd!', \PASSWORD_DEFAULT);
        $user = new User('usr_1', 'admin', 'role_admin', $hash, 'v0.0.0');

        $this->assertTrue($user->verifyPassword('OldP4ssw0rd!'));
        $this->assertFalse($user->verifyPassword('WrongPass'));

        $user->changePassword('NewP4ssw0rd!12');
        $this->assertTrue($user->verifyPassword('NewP4ssw0rd!12'));
        $this->assertFalse($user->verifyPassword('OldP4ssw0rd!'));
    }

    #[Test]
    public function itEnforcesPasswordLengthBoundaries(): void
    {
        $user = new User('usr_1', 'admin', 'role_admin', 'hash', 'v0.0.0');

        // KILLT DEN MUTANTEN (<= 8 Zeichen): 8 Zeichen MÜSSEN erlaubt sein!
        $user->changePassword('12345678');
        $this->assertTrue($user->verifyPassword('12345678'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Das neue Passwort muss mindestens 8 Zeichen lang sein.');

        // 7 Zeichen knallen!
        $user->changePassword('1234567');
    }

    #[Test]
    public function itAllowsRenamingAndUpdatingRoleWithTrimming(): void
    {
        $user = new User('usr_1', 'old_name', 'role_1', 'hash', 'v0');

        // KILLT DEN MUTANTEN (trim() validation):
        $user->rename('  new_name  ');
        $user->changeRole('  role_2  ');

        $this->assertSame('new_name', $user->getUsername());
        $this->assertSame('role_2', $user->getRoleId());
    }

    #[Test]
    #[DataProvider('invalidUserPropsProvider')]
    public function itThrowsExceptionsForEmptyNamesAndRoles(string $method, string $invalidValue): void
    {
        $user = new User('usr_1', 'name', 'role', 'hash', 'v0');

        $this->expectException(DomainException::class);
        $user->$method($invalidValue);
    }

    public static function invalidUserPropsProvider(): Iterator
    {
        yield 'empty name' => ['rename', '   '];
        yield 'empty role' => ['changeRole', '   '];
    }
}
