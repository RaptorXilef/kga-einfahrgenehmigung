<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageRoles;

use App\Application\Exception\ValidationException;
use App\Modules\Identity\Application\UseCases\ManageRoles\DeleteRoleRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteRoleRequest::class)]
final class DeleteRoleRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidArray(): void
    {
        $post = ['group_id' => '  role_custom  '];
        $request = DeleteRoleRequest::fromArray($post);

        $this->assertSame('role_custom', $request->roleId);
    }

    #[Test]
    #[DataProvider('invalidPostProvider')]
    public function itThrowsExceptionOnInvalidData(array $post): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Fehler: Ungültige oder fehlende Rollen-ID.');

        DeleteRoleRequest::fromArray($post);
    }

    public static function invalidPostProvider(): Iterator
    {
        yield 'missing id' => [[]];
        yield 'empty id' => [['group_id' => '   ']];
        yield 'invalid chars' => [['group_id' => 'role_!@#']];
    }
}
