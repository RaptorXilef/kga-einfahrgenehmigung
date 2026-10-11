<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Modules\System\Infrastructure\Storage\FileLockManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileLockManager::class)]
final class FileLockManagerTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = \sys_get_temp_dir() . '/kga_lock_test_' . \uniqid();
        @\mkdir($this->tempRoot . '/logs', 0o777, true);
    }

    protected function tearDown(): void
    {
        @\unlink($this->tempRoot . '/logs/test_job.lock');
        @\rmdir($this->tempRoot . '/logs');
        @\rmdir($this->tempRoot);
        parent::tearDown();
    }

    #[Test]
    public function itExecutesOperationWithFileLockAndReturnsResult(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('get')->with('root_path')->willReturn($this->tempRoot);

        $manager = new FileLockManager($config);

        $result = $manager->executeWithLock('test_job', fn () => 'Success_123');

        $this->assertSame('Success_123', $result);
        $this->assertFileExists($this->tempRoot . '/logs/test_job.lock');
    }
}
