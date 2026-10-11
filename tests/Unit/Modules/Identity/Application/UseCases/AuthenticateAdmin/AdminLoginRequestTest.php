<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\AuthenticateAdmin;

use App\Application\Exception\ValidationException;
use App\Modules\Identity\Application\UseCases\AuthenticateAdmin\AdminLoginRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdminLoginRequest::class)]
final class AdminLoginRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidArray(): void
    {
        $post = [
            'user' => '  admin_user  ',
            'pass' => 'secret123',
            'code' => '  1234  ',
        ];

        $request = AdminLoginRequest::fromArray($post);

        // KILLT MUTANTEN: Prüft, ob trim() korrekt angewendet wurde
        $this->assertSame('admin_user', $request->username);
        $this->assertSame('secret123', $request->password);
        $this->assertSame('1234', $request->redirectCode);
    }

    #[Test]
    #[DataProvider('invalidPostProvider')]
    public function itThrowsExceptionOnMissingOrEmptyCredentials(array $post): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Bitte geben Sie einen Benutzernamen und ein Passwort ein.');

        AdminLoginRequest::fromArray($post);
    }

    public static function invalidPostProvider(): Iterator
    {
        yield 'missing all' => [[]];
        yield 'missing pass' => [['user' => 'admin']];
        yield 'missing user' => [['pass' => 'secret']];
        yield 'empty string user' => [['user' => '   ', 'pass' => 'secret']];
        yield 'empty string pass' => [['user' => 'admin', 'pass' => '']];
    }
}
