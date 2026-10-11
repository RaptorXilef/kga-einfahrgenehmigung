<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\ApiCsrfMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\JsonResponse;
use App\Application\Session\SessionManager;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ApiCsrfMiddleware::class)]
final class ApiCsrfMiddlewareTest extends TestCase
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
    public function itReturnsUnauthorizedJsonOnInvalidToken(): void
    {
        $session = new SessionManager(new SystemClock());
        $session->initCsrfToken();

        $middleware = new ApiCsrfMiddleware($session);
        $request = new ServerRequest(server: ['REQUEST_METHOD' => 'POST']); // Kein Token mitgeschickt

        /** @var JsonResponse $response */
        $response = $middleware->process($request, fn () => new HtmlResponse('OK'));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->statusCode);
        $this->assertFalse($response->data['success']);
    }

    #[Test]
    public function itPassesRequestIfTokenIsProvidedViaHeader(): void
    {
        $session = new SessionManager(new SystemClock());
        $validToken = $session->initCsrfToken();

        $middleware = new ApiCsrfMiddleware($session);
        $request = new ServerRequest(
            server: ['REQUEST_METHOD' => 'POST', 'HTTP_X_CSRF_TOKEN' => $validToken],
        );

        $response = $middleware->process($request, fn () => new HtmlResponse('OK'));
        $this->assertInstanceOf(HtmlResponse::class, $response);
    }
}
