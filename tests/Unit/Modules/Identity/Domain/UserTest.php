<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\User;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    #[Test]
    public function itCreatesAUserAndAllowsRetrievingProperties(): void
    {
        $user = new User('usr_123', 'max_m', 'role_admin', 'hash123', 'v1.0.0');

        $this->assertSame('usr_123', $user->id);
        $this->assertSame('max_m', $user->getUsername());
        $this->assertSame('role_admin', $user->getRoleId());
    }

    #[Test]
    public function itVerifiesAndChangesPasswordCorrectly(): void
    {
        $hash = \password_hash('OldP4ssw0rd!', \PASSWORD_DEFAULT);
        $user = new User('usr_1', 'admin', 'role_admin', $hash, 'v0.0.0');

        $this->assertTrue($user->verifyPassword('OldP4ssw0rd!'));
        $this->assertFalse($user->verifyPassword('WrongPass'));

        $user->changePassword('NewP4ssw0rd!');
        $this->assertTrue($user->verifyPassword('NewP4ssw0rd!'));
        $this->assertFalse($user->verifyPassword('OldP4ssw0rd!'));
    }

    #[Test]
    #[DataProvider('invalidUserPropsProvider')]
    public function itThrowsExceptionsForEmptyNamesAndRoles(string $method, string $invalidValue): void
    {
        $user = new User('usr_1', 'name', 'role', 'hash', 'v0');

        $this->expectException(DomainException::class);
        $user->$method($invalidValue);
    }

    public static function invalidUserPropsProvider(): array
    {
        return [
            'empty name' => ['rename', '   '],
            'empty role' => ['changeRole', ''],
        ];
    }
}
