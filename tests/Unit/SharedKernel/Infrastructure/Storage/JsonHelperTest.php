<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Storage;

use App\SharedKernel\Infrastructure\Storage\JsonHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(JsonHelper::class)]
final class JsonHelperTest extends TestCase
{
    #[Test]
    public function itDecodesValidJsonAndStripsComments(): void
    {
        $helper = new JsonHelper();

        $json = <<<JSON
            {
                // Einlaufender Kommentar
                "key1": "value1", /* Inline Kommentar */
                "key2": 42
            }
            JSON;

        $data = $helper->decode($json);

        $this->assertSame(['key1' => 'value1', 'key2' => 42], $data);
        $this->assertSame([], $helper->decode('   '));
    }

    #[Test]
    public function itThrowsExceptionOnCorruptJson(): void
    {
        $helper = new JsonHelper();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kritischer Fehler: JSON-Datenstruktur ist korrupt');

        $helper->decode('{ "key": "missing_quote }');
    }

    #[Test]
    public function itReadsFileAndDecodesCorrectly(): void
    {
        $helper = new JsonHelper();
        $tmpFile = \tempnam(\sys_get_temp_dir(), 'json_test_');
        \file_put_contents($tmpFile, '{"valid": true}');

        $data = $helper->read($tmpFile);
        $this->assertSame(['valid' => true], $data);

        @\unlink($tmpFile);
    }

    #[Test]
    public function itReturnsEmptyArrayIfFileDoesNotExist(): void
    {
        $helper = new JsonHelper();
        $this->assertSame([], $helper->read('/tmp/does_not_exist_404.json'));
    }
}
