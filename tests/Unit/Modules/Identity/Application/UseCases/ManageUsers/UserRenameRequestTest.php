<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;
use App\Modules\Identity\Application\UseCases\ManageUsers\UserRenameRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserRenameRequest::class)]
final class UserRenameRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidArray(): void
    {
        $post = ['user_id' => 'usr_123', 'new_username' => '  max_m  '];
        $request = UserRenameRequest::fromArray($post);

        $this->assertSame('usr_123', $request->userId);
        $this->assertSame('max_m', $request->newUsername);
    }

    #[Test]
    #[DataProvider('invalidPostProvider')]
    public function itThrowsExceptionOnInvalidData(array $post, string $expectedMessage): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        UserRenameRequest::fromArray($post);
    }

    public static function invalidPostProvider(): Iterator
    {
        yield 'missing user' => [['new_username' => 'max'], 'Fehler: Kein Benutzer ausgewählt.'];
        yield 'sys prefix' => [['user_id' => 'usr_123', 'new_username' => 'sys_admin'], 'Fehler: Namen mit dem Präfix "sys_" sind für das System reserviert.'];
        yield 'empty name' => [['user_id' => 'usr_123', 'new_username' => '   '], 'Fehler: Der neue Login-Name darf nicht leer sein.'];
    }
}
