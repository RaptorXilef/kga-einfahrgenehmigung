<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\SystemMaintenanceMiddleware;
use App\Application\Response\HtmlResponse;
use App\Contracts\System\StorageBootstrapperInterface;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemMaintenanceMiddleware::class)]
final class SystemMaintenanceMiddlewareTest extends TestCase
{
    private string $tempLogFile;

    private string $originalErrorLog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalErrorLog = (string) \ini_get('error_log');
        $this->tempLogFile = \sys_get_temp_dir() . '/test_error_log_' . \uniqid('', true) . '.log';
        \ini_set('error_log', $this->tempLogFile);
    }

    protected function tearDown(): void
    {
        \ini_set('error_log', $this->originalErrorLog);

        if (\file_exists($this->tempLogFile)) {
            @\unlink($this->tempLogFile);
        }

        parent::tearDown();
    }

    #[Test]
    public function itBootstrapsStorageAndPassesRequest(): void
    {
        $bootstrapper = $this->createMock(StorageBootstrapperInterface::class);
        $bootstrapper->expects($this->once())->method('bootstrap');

        $middleware = new SystemMaintenanceMiddleware($bootstrapper);
        $request = new ServerRequest();

        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function itCatchesExceptionLogsItAndPassesRequest(): void
    {
        $bootstrapper = $this->createStub(StorageBootstrapperInterface::class);
        $bootstrapper->method('bootstrap')->willThrowException(new Exception('DB Crash'));

        $middleware = new SystemMaintenanceMiddleware($bootstrapper);
        $request = new ServerRequest();

        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('STILL OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);

        // Wir prüfen, ob die Exception sauber als Native-Error geloggt wurde
        $logContent = \file_get_contents($this->tempLogFile);
        $this->assertIsString($logContent);
        $this->assertStringContainsString('Bootstrapping Warning: DB Crash', $logContent);
    }
}
