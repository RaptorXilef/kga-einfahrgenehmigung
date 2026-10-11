<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Application\UseCases\ManageMails;

use App\Application\Exception\ValidationException;
use App\Modules\System\Application\UseCases\ManageMails\ResendMailRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ResendMailRequest::class)]
final class ResendMailRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidArray(): void
    {
        $request = ResendMailRequest::fromArray(['timestamp' => '  2026-10-15 12:00:00  ']);
        $this->assertSame('2026-10-15 12:00:00', $request->timestamp);
    }

    #[Test]
    #[DataProvider('invalidPostProvider')]
    public function itThrowsExceptionOnInvalidData(array $post): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Fehler: Ungültiger oder fehlender Parameter (timestamp).');

        ResendMailRequest::fromArray($post);
    }

    public static function invalidPostProvider(): Iterator
    {
        yield 'missing' => [[]];
        yield 'empty' => [['timestamp' => '  ']];
        yield 'invalid chars' => [['timestamp' => '2026-10-15; DELETE FROM users;']];
    }
}
