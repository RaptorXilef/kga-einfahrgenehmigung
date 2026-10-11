<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Middleware;

use App\Application\Http\ServerRequest;
use App\Application\Middleware\FormExceptionHandlerMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FormExceptionHandlerMiddleware::class)]
final class FormExceptionHandlerMiddlewareTest extends TestCase
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
    public function itPassesThroughIfNoExceptionIsThrown(): void
    {
        $session = new SessionManager(new SystemClock());
        $middleware = new FormExceptionHandlerMiddleware($session);
        $request = new ServerRequest();

        $response = $middleware->process($request, fn (): HtmlResponse => new HtmlResponse('OK'));
        $this->assertInstanceOf(HtmlResponse::class, $response);
    }

    #[Test]
    public function itCatchesDomainExceptionAndRedirectsOnPostRequest(): void
    {
        $session = new SessionManager(new SystemClock());
        $middleware = new FormExceptionHandlerMiddleware($session);

        $request = new ServerRequest(
            post: ['username' => 'Max', 'password' => 'secret'],
            server: ['REQUEST_METHOD' => 'POST', 'HTTP_REFERER' => '/formular'],
        );

        $nextThatThrows = fn () => throw new DomainException('Fehler im Formular');

        /** @var RedirectResponse $response */
        $response = $middleware->process($request, $nextThatThrows);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/formular', $response->url);

        // Flash message was set
        $this->assertSame(['error' => ['Fehler im Formular']], $_SESSION['flashes']);

        // Form Data was saved safely (password stripped)
        $formData = $session->getFormData();
        $this->assertSame(['username' => 'Max'], $formData);
    }

    #[Test]
    public function itThrowsExceptionFurtherIfRequestIsApiOrGet(): void
    {
        $session = new SessionManager(new SystemClock());
        $middleware = new FormExceptionHandlerMiddleware($session);

        $request = new ServerRequest(server: ['REQUEST_URI' => '/api/test']);
        $nextThatThrows = fn () => throw new DomainException('API Fehler');

        $this->expectException(DomainException::class);
        $middleware->process($request, $nextThatThrows);
    }
}
