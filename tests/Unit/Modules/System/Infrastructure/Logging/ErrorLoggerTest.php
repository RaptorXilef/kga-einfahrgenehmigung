<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Infrastructure\Logging;

use App\Contracts\Config\ConfigInterface;
use App\Modules\System\Infrastructure\Logging\ErrorLogger;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ErrorLogger::class)]
final class ErrorLoggerTest extends TestCase
{
    private string $tempRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempRoot = \sys_get_temp_dir() . '/kga_log_' . \uniqid();
    }

    protected function tearDown(): void
    {
        @\unlink($this->tempRoot . '/logs/system_error.log');
        @\rmdir($this->tempRoot . '/logs');
        @\rmdir($this->tempRoot);
        parent::tearDown();
    }

    #[Test]
    public function itLogsThrowableToFile(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getString')->willReturn($this->tempRoot);

        $logger = new ErrorLogger($config);
        $logger->logThrowable(new Exception('Test Exception Message'));

        $logFile = $this->tempRoot . '/logs/system_error.log';
        $this->assertFileExists($logFile);

        $content = \file_get_contents($logFile);
        $this->assertIsString($content);
        $this->assertStringContainsString('Test Exception Message', $content);
        $this->assertStringContainsString('Exception', $content);
    }
}
