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

        // Fängt den nativen Output von \error_log() sicher ab, um den Test nicht crashen zu lassen!
        $this->expectOutputRegex('/Bootstrapping Warning: DB Crash/');

        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('STILL OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }
}
