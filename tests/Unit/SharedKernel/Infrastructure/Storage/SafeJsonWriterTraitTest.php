<?php

declare(strict_types=1);

namespace App\Tests\Unit\SharedKernel\Infrastructure\Storage;

use App\SharedKernel\Infrastructure\Storage\SafeJsonWriterTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SafeJsonWriterTrait::class)]
final class SafeJsonWriterTraitTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = \sys_get_temp_dir() . '/kga_json_' . \uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        @\unlink($this->tempFile);
        parent::tearDown();
    }

    #[Test]
    public function itWritesJsonAtomically(): void
    {
        $writer = new class {
            use SafeJsonWriterTrait;

            public function write(string $path, array $data): void
            {
                $this->writeJsonSafely($path, $data);
            }
        };

        $writer->write($this->tempFile, ['test' => true]);

        $this->assertFileExists($this->tempFile);
        $this->assertSame("{\n    \"test\": true\n}", \file_get_contents($this->tempFile));
    }

    #[Test]
    public function itThrowsExceptionOnInvalidPath(): void
    {
        $writer = new class {
            use SafeJsonWriterTrait;

            public function write(string $path, array $data): void
            {
                $this->writeJsonSafely($path, $data);
            }
        };

        $this->expectException(RuntimeException::class);
        $writer->write('/root/forbidden_dir_that_does_not_exist/file.json', ['test' => true]);
    }
}
