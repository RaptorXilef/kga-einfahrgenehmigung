<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Application\UseCases\ManageBackups;

use App\Application\Exception\ValidationException;
use App\Modules\System\Application\UseCases\ManageBackups\TruncateTargetRequest;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TruncateTargetRequest::class)]
final class TruncateTargetRequestTest extends TestCase
{
    #[Test]
    public function itCreatesRequestFromValidArray(): void
    {
        $request = TruncateTargetRequest::fromArray(['target' => '  mail_log  ']);
        $this->assertSame('mail_log', $request->target);
    }

    #[Test]
    #[DataProvider('invalidPostProvider')]
    public function itThrowsExceptionOnInvalidData(array $post): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Fehler: Kein Zielbereich ausgewählt.');

        TruncateTargetRequest::fromArray($post);
    }

    public static function invalidPostProvider(): Iterator
    {
        yield 'missing' => [[]];
        yield 'empty' => [['target' => '  ']];
    }
}
