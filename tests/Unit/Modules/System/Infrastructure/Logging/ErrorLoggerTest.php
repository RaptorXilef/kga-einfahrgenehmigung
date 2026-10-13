<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\System\Infrastructure\Logging;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\System\Infrastructure\Logging\ErrorLogger;
use DateTimeImmutable;
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

        $sysTemp = \realpath(\sys_get_temp_dir()) ?: \sys_get_temp_dir();
        $this->tempRoot = $sysTemp . \DIRECTORY_SEPARATOR . 'kga_log_' . \uniqid();

        // WICHTIG: Das 'logs' Verzeichnis MUSS vorher existieren, file_put_contents erstellt keine Ordner!
        @\mkdir($this->tempRoot . \DIRECTORY_SEPARATOR . 'logs', 0o777, true);

        $this->tempRoot = \realpath($this->tempRoot) ?: $this->tempRoot;
    }

    protected function tearDown(): void
    {
        @\unlink($this->tempRoot . \DIRECTORY_SEPARATOR . 'logs' . \DIRECTORY_SEPARATOR . 'system_error.log');
        @\rmdir($this->tempRoot . \DIRECTORY_SEPARATOR . 'logs');
        @\rmdir($this->tempRoot);
        parent::tearDown();
    }

    #[Test]
    public function itLogsThrowableToFile(): void
    {
        $config = $this->createStub(ConfigInterface::class);

        // Narrensicher: Wir fangen get UND getString ab, falls die Implementierung sich ändert
        $config->method('get')->willReturnCallback(fn (string $key, mixed $def = null): mixed => $key === 'root_path' ? $this->tempRoot : $def);
        $config->method('getString')->willReturnCallback(fn (string $key, string $def = ''): string => $key === 'root_path' ? $this->tempRoot : $def);

        $clock = $this->createStub(ClockInterface::class);
        $clock->method('now')->willReturn(new DateTimeImmutable('2026-10-15 12:00:00'));

        $logger = new ErrorLogger($config, $clock);
        $logger->logThrowable(new Exception('Test Exception Message'));

        $logFile = $this->tempRoot . \DIRECTORY_SEPARATOR . 'logs' . \DIRECTORY_SEPARATOR . 'system_error.log';
        $this->assertFileExists($logFile);

        $content = \file_get_contents($logFile);
        $this->assertIsString($content);
        $this->assertStringContainsString('Test Exception Message', $content);
        $this->assertStringContainsString('Exception', $content);
    }
}
