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
    public function itCreatesARoleAndAllowsRetrievingItsProperties(): void
    {
        $role = new Role('role_123', 'Vorstand', ['permits.view', 'system.manage']);

        $this->assertSame('role_123', $role->id);
        $this->assertSame('Vorstand', $role->getName());
        $this->assertSame(['permits.view', 'system.manage'], $role->getPermissions());
    }

    #[Test]
    public function itAllowsRenamingTheRole(): void
    {
        $role = new Role('role_123', 'Old Name', []);
        $role->rename('New Admin Name');

        $this->assertSame('New Admin Name', $role->getName());
    }

    #[Test]
    public function itThrowsExceptionWhenRenamingToAnEmptyString(): void
    {
        $role = new Role('role_123', 'Valid Name', []);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Der Rollenname darf nicht leer sein.');

        $role->rename('   ');
    }

    #[Test]
    public function itAllowsUpdatingPermissions(): void
    {
        $role = new Role('role_123', 'Name', ['old.perm']);
        $role->updatePermissions(['new.perm1', 'new.perm2']);

        $this->assertSame(['new.perm1', 'new.perm2'], $role->getPermissions());
    }

    #[Test]
    public function itThrowsExceptionIfInstantiatedWithEmptyName(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Der Rollenname darf nicht leer sein.');

        new Role('role_123', '   ', []);
    }
}
