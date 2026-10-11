<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageRoles;

use App\Application\Exception\ValidationException;
use App\Modules\Identity\Application\UseCases\ManageRoles\RoleSaveRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RoleSaveRequest::class)]
final class RoleSaveRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidArray(): void
    {
        $post = [
            'group_id' => '  role_custom  ',
            'group_name' => '  Custom Role  ',
            'inherit_group' => 'role_admin',
            'perms' => ['stats.view', 'permits.create'],
        ];

        $request = RoleSaveRequest::fromArray($post);

        $this->assertSame('role_custom', $request->roleId);
        $this->assertSame('Custom Role', $request->roleName);
        $this->assertSame('role_admin', $request->inheritRole);
        $this->assertSame(['stats.view', 'permits.create'], $request->permissions);
    }

    #[Test]
    #[DataProvider('invalidPostProvider')]
    public function itThrowsExceptionOnInvalidData(array $post, string $expectedMessage): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        RoleSaveRequest::fromArray($post);
    }

    public static function invalidPostProvider(): Iterator
    {
        yield 'missing role name' => [
            ['group_id' => 'role_1', 'group_name' => '   '],
            'Fehler: Der Rollenname darf nicht leer sein.',
        ];
        yield 'empty array' => [
            [],
            'Fehler: Der Rollenname darf nicht leer sein.',
        ];
    }
}
