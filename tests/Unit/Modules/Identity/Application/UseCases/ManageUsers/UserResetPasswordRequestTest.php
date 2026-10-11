<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;
use App\Modules\Identity\Application\UseCases\ManageUsers\UserResetPasswordRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UserResetPasswordRequest::class)]
final class UserResetPasswordRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidArray(): void
    {
        $post = [
            'user_id' => 'usr_123',
            'password' => 'secret123',
            'password_repeat' => 'secret123',
        ];

        $request = UserResetPasswordRequest::fromArray($post);

        $this->assertSame('usr_123', $request->userId);
        $this->assertSame('secret123', $request->newPassword);
    }

    #[Test]
    #[DataProvider('invalidPostProvider')]
    public function itThrowsExceptionOnInvalidData(array $post, string $expectedMessage): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        UserResetPasswordRequest::fromArray($post);
    }

    public static function invalidPostProvider(): Iterator
    {
        yield 'missing user_id' => [
            ['password' => 'secret123', 'password_repeat' => 'secret123'],
            'Fehler: Keine Benutzer-ID übergeben.',
        ];
        yield 'password mismatch' => [
            ['user_id' => '1', 'password' => 'secret123', 'password_repeat' => 'secret999'],
            'Fehler: Passwörter nicht identisch.',
        ];
        yield 'password too short' => [
            ['user_id' => '1', 'password' => 'short', 'password_repeat' => 'short'],
            'Fehler: Passwort muss mindestens 8 Zeichen lang sein.',
        ];
    }
}
