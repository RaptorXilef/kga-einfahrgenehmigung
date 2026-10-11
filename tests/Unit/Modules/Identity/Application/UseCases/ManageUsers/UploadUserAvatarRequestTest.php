<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Identity\Application\UseCases\ManageUsers;

use App\Application\Exception\ValidationException;
use App\Modules\Identity\Application\UseCases\ManageUsers\UploadUserAvatarRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UploadUserAvatarRequest::class)]
final class UploadUserAvatarRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidData(): void
    {
        $post = ['user_id' => '  usr_123  '];
        $files = ['avatar' => ['name' => 'test.png', 'error' => 0]];

        $request = UploadUserAvatarRequest::fromRequest($post, $files);

        $this->assertSame('usr_123', $request->userId);
        $this->assertSame(0, $request->file['error']);
    }

    #[Test]
    #[DataProvider('invalidDataProvider')]
    public function itThrowsExceptionOnInvalidData(array $post, array $files, string $expectedMessage): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($expectedMessage);

        UploadUserAvatarRequest::fromRequest($post, $files);
    }

    public static function invalidDataProvider(): Iterator
    {
        yield 'missing user' => [[], ['avatar' => ['error' => 0]], 'Fehler: Fehlende Benutzer-ID.'];
        yield 'missing file' => [['user_id' => 'usr_123'], [], 'Fehler: Ungültiger oder fehlender Datei-Upload.'];
        yield 'upload error' => [['user_id' => 'usr_123'], ['avatar' => ['error' => 4]], 'Fehler: Ungültiger oder fehlender Datei-Upload.'];
    }
}
