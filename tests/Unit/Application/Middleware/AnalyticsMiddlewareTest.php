<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AnalyticsTrackerInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AnalyticsMiddleware::class)]
final class AnalyticsMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $_SESSION = [];
    }

    #[Test]
    public function itSkipsTrackingInLocalEnvironment(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBool')->willReturnMap([['is_local_env', false, true]]);

        $tracker = $this->createMock(AnalyticsTrackerInterface::class);
        $tracker->expects($this->never())->method('trackPageView'); // Darf nicht tracken

        $middleware = new AnalyticsMiddleware(
            $config,
            new SessionManager(new SystemClock()),
            $this->createStub(ClockInterface::class),
            $tracker,
        );

        $request = new ServerRequest(server: ['REQUEST_URI' => '/check']);
        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function itSkipsTrackingForApiAndCronRoutes(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBool')->willReturnMap([['is_local_env', false, false]]);

        $tracker = $this->createMock(AnalyticsTrackerInterface::class);
        $tracker->expects($this->never())->method('trackPageView');

        $middleware = new AnalyticsMiddleware(
            $config,
            new SessionManager(new SystemClock()),
            $this->createStub(ClockInterface::class),
            $tracker,
        );

        // API Pfad sollte ignoriert werden
        $request = new ServerRequest(server: ['REQUEST_URI' => '/api/ping']);
        $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));
    }

    #[Test]
    public function itSkipsTrackingIfConsentIsMissingOrDenied(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBool')->willReturnMap([['is_local_env', false, false]]);

        $tracker = $this->createMock(AnalyticsTrackerInterface::class);
        $tracker->expects($this->never())->method('trackPageView');

        $middleware = new AnalyticsMiddleware(
            $config,
            new SessionManager(new SystemClock()),
            $this->createStub(ClockInterface::class),
            $tracker,
        );

        // Fall 1: Kein Cookie
        $request1 = new ServerRequest(server: ['REQUEST_URI' => '/check']);
        $middleware->process($request1, fn (): HtmlResponse => new HtmlResponse('OK'));

        // Fall 2: Consent verweigert
        $request2 = new ServerRequest(cookie: ['kga_cookie_consent' => '{"analytics": false}'], server: ['REQUEST_URI' => '/check']);
        $middleware->process($request2, fn (): HtmlResponse => new HtmlResponse('OK'));
    }

    #[Test]
    public function itTracksPageViewIfConsentIsGiven(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBool')->willReturnMap([['is_local_env', false, false]]);
        $config->method('getArray')->willReturnMap([['ga4_server_side', [], ['measurement_id' => 'G-123', 'api_secret' => 'SEC']]]);
        $config->method('getBaseUrl')->willReturn('https://test.de/');

        // Echte SessionManager Instanz nutzen und $_SESSION manuell füllen (Kein Mocking nötig!)
        $_SESSION['ga4_client_id'] = 'client-123';
        $_SESSION['ga4_session_id'] = 1600000000;
        $session = new SessionManager(new SystemClock());

        $tracker = $this->createMock(AnalyticsTrackerInterface::class);
        $tracker->expects($this->once())
            ->method('trackPageView')
            ->with('client-123', 1600000000, 'https://test.de/check', 'Check');

        $middleware = new AnalyticsMiddleware($config, $session, $this->createStub(ClockInterface::class), $tracker);

        $request = new ServerRequest(cookie: ['kga_cookie_consent' => '{"analytics": true}'], server: ['REQUEST_URI' => '/check']);
        $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));
    }

    #[Test]
    public function itCatchesExceptionsQuietlyAndProceeds(): void
    {
        $config = $this->createStub(ConfigInterface::class);
        $config->method('getBool')->willReturnMap([['is_local_env', false, false]]);
        $config->method('getArray')->willReturnMap([['ga4_server_side', [], ['measurement_id' => 'G-123', 'api_secret' => 'SEC']]]);

        $tracker = $this->createStub(AnalyticsTrackerInterface::class);
        $tracker->method('trackPageView')->willThrowException(new Exception('Network Error'));

        $middleware = new AnalyticsMiddleware(
            $config,
            new SessionManager(new SystemClock()),
            $this->createStub(ClockInterface::class),
            $tracker,
        );

        $request = new ServerRequest(cookie: ['kga_cookie_consent' => '{"analytics": true}'], server: ['REQUEST_URI' => '/check']);

        // Muss ohne Exception durchlaufen!
        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }
}
