<?php
declare(strict_types=1);
namespace App\Tests\Unit\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Role;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Role::class)]
final class RoleTest extends TestCase
{
    #[Test]
    public function it_creates_a_role_and_allows_retrieving_its_properties(): void
    {
        $role = new Role('role_123', 'Vorstand', ['permits.view', 'system.manage']);

        self::assertSame('role_123', $role->id);
        self::assertSame('Vorstand', $role->getName());
        self::assertSame(['permits.view', 'system.manage'], $role->getPermissions());
    }

    #[Test]
    public function it_allows_renaming_the_role(): void
    {
        $role = new Role('role_123', 'Old Name', []);
        $role->rename('New Admin Name');

        self::assertSame('New Admin Name', $role->getName());
    }

    #[Test]
    public function it_throws_exception_when_renaming_to_an_empty_string(): void
    {
        $role = new Role('role_123', 'Valid Name', []);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Der Rollenname darf nicht leer sein.');

        $role->rename('   ');
    }

    #[Test]
    public function it_allows_updating_permissions(): void
    {
        $role = new Role('role_123', 'Name', ['old.perm']);
        $role->updatePermissions(['new.perm1', 'new.perm2']);

        self::assertSame(['new.perm1', 'new.perm2'], $role->getPermissions());
    }
}
