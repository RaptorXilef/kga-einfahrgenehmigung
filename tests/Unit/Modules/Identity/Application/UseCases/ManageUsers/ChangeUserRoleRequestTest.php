<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;
use App\Modules\Identity\Application\UseCases\ManageUsers\ChangeUserRoleRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChangeUserRoleRequest::class)]
final class ChangeUserRoleRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidArray(): void
    {
        $post = ['user_id' => '  usr_123  ', 'group' => '  role_admin  '];
        $request = ChangeUserRoleRequest::fromArray($post);

        $this->assertSame('usr_123', $request->userId);
        $this->assertSame('role_admin', $request->roleId);
    }

    #[Test]
    #[DataProvider('invalidPostProvider')]
    public function itThrowsExceptionOnInvalidData(array $post, string $expectedMessage): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        ChangeUserRoleRequest::fromArray($post);
    }

    public static function invalidPostProvider(): Iterator
    {
        yield 'missing user' => [['group' => 'role_admin'], 'Fehler: Kein Benutzer ausgewählt.'];
        yield 'empty user' => [['user_id' => '  ', 'group' => 'role_admin'], 'Fehler: Kein Benutzer ausgewählt.'];
        yield 'missing role' => [['user_id' => 'usr_123'], 'Fehler: Keine Rolle ausgewählt.'];
        yield 'empty role' => [['user_id' => 'usr_123', 'group' => '  '], 'Fehler: Keine Rolle ausgewählt.'];
    }
}
