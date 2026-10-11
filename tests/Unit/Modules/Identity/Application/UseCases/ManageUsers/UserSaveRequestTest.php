<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;
use App\Modules\Identity\Application\UseCases\ManageUsers\UserSaveRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserSaveRequest::class)]
final class UserSaveRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidArrayAndFiles(): void
    {
        $post = [
            'username' => '  max_m  ',
            'password' => 'secret123',
            'password_repeat' => 'secret123',
            'group' => 'role_admin',
        ];
        $files = [
            'avatar' => ['name' => 'test.png', 'error' => 0],
        ];

        $request = UserSaveRequest::fromArray($post, $files);

        $this->assertSame('max_m', $request->username);
        $this->assertSame('secret123', $request->password);
        $this->assertSame('role_admin', $request->group);
        $this->assertSame(['name' => 'test.png', 'error' => 0], $request->avatar);
    }

    #[Test]
    #[DataProvider('invalidPostProvider')]
    public function itThrowsExceptionOnInvalidData(array $post, string $expectedMessage): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        UserSaveRequest::fromArray($post);
    }

    public static function invalidPostProvider(): Iterator
    {
        yield 'empty username' => [
            ['username' => '   ', 'password' => 'secret123', 'password_repeat' => 'secret123'],
            'Fehler: Der Benutzername darf nicht leer sein.',
        ];
        yield 'sys_ prefix' => [
            ['username' => 'sys_admin', 'password' => 'secret123', 'password_repeat' => 'secret123'],
            'Fehler: Namen mit dem Präfix "sys_" sind für das System reserviert.',
        ];
        yield 'empty password' => [
            ['username' => 'max', 'password' => '', 'password_repeat' => ''],
            'Fehler: Das Passwort darf nicht leer sein.',
        ];
        yield 'password mismatch' => [
            ['username' => 'max', 'password' => 'secret123', 'password_repeat' => 'secret999'],
            'Fehler: Die Passwörter stimmen nicht überein.',
        ];
        yield 'password too short' => [
            ['username' => 'max', 'password' => 'short', 'password_repeat' => 'short'],
            'Fehler: Das Passwort muss mindestens 8 Zeichen lang sein.',
        ];
    }

    #[Test]
    public function itIgnoresInvalidAvatarUploads(): void
    {
        $post = ['username' => 'max', 'password' => 'secret123', 'password_repeat' => 'secret123'];
        $files = ['avatar' => ['name' => 'test.png', 'error' => 4]]; // UPLOAD_ERR_NO_FILE

        $request = UserSaveRequest::fromArray($post, $files);

        $this->assertNull($request->avatar);
    }
}
