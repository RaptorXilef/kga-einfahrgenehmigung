<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsrfMiddleware::class)]
final class CsrfMiddlewareTest extends TestCase
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
    public function itPassesGetRequestsWithoutCheckingToken(): void
    {
        $session = new SessionManager(new SystemClock());
        $middleware = new CsrfMiddleware($session, '/fallback');

        $request = new ServerRequest(server: ['REQUEST_METHOD' => 'GET']);
        $response = $middleware->process($request, fn () => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function itPassesPostRequestsWithValidToken(): void
    {
        $session = new SessionManager(new SystemClock());
        $validToken = $session->initCsrfToken();

        $middleware = new CsrfMiddleware($session, '/fallback');

        $request = new ServerRequest(
            post: ['csrf_token' => $validToken],
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $response = $middleware->process($request, fn () => new HtmlResponse('OK'));

        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function itRedirectsAndSavesFormDataIfTokenIsInvalid(): void
    {
        $session = new SessionManager(new SystemClock());
        $session->initCsrfToken(); // Generiert ein Token in der Session

        $middleware = new CsrfMiddleware($session, '/fallback');

        $request = new ServerRequest(
            post: ['csrf_token' => 'invalid_token', 'action' => 'login', 'username' => 'Max'],
            server: ['REQUEST_METHOD' => 'POST'],
        );

        /** @var RedirectResponse $response */
        $response = $middleware->process($request, fn () => new HtmlResponse('OK'));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/fallback', $response->url);

        // Prüft, ob Form-Daten sicherheitsbereinigt gespeichert wurden (PRG Pattern)
        $formData = $session->getFormData();
        $this->assertSame(['username' => 'Max'], $formData);
        $this->assertArrayNotHasKey('csrf_token', $formData);
        $this->assertArrayNotHasKey('action', $formData);
    }
}
