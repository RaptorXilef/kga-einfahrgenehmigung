<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\CronAuthMiddleware;
use App\Application\Response\EmptyResponse;
use App\Application\Response\HtmlResponse;
use App\Contracts\Config\ConfigInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CronAuthMiddleware::class)]
final class CronAuthMiddlewareTest extends TestCase
{
    #[Test]
    public function itPassesIfTokenMatchesConfigInWebRequest(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getString')->willReturn('secret-cron');

        $middleware = new CronAuthMiddleware($config);

        // server: [] simuliert einen echten Web-Request (ohne argc)
        $request = new ServerRequest(get: ['token' => 'secret-cron'], server: []);

        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function itBlocksExecutionIfTokenIsWrongOrMissingInWebRequest(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getString')->willReturn('secret-cron');

        $middleware = new CronAuthMiddleware($config);
        $request = new ServerRequest(get: ['token' => 'wrong-token'], server: []);

        /** @var EmptyResponse $response */
        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(EmptyResponse::class, $response);
        $this->assertSame(403, $response->status);
    }

    #[Test]
    public function itPassesWithoutTokenIfExecutedInCliEnvironment(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getString')->willReturn('secret-cron');

        $middleware = new CronAuthMiddleware($config);

        // server: ['argc' => 1] simuliert einen reinen CLI-Aufruf über die Konsole
        $request = new ServerRequest(get: [], server: ['argc' => 1]);

        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }
}
