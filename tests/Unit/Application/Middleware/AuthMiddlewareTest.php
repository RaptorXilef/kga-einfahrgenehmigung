<?php

declare(strict_types=1);

use App\Application\Http\ServerRequest;
use App\Application\Middleware\AuthMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthorizationInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;

covers(AuthMiddleware::class);

beforeEach(function (): void {
    if (\session_status() === \PHP_SESSION_NONE) {
        \session_start();
    }
    // Session für jeden Test sauber leeren
    $_SESSION = [];
});

test('it allows admin access if user is properly logged in', function (): void {
    /** @var AuthorizationInterface&\PHPUnit\Framework\MockObject\Stub $auth */
    $auth = $this->createStub(AuthorizationInterface::class);
    $auth->method('isLoggedIn')->willReturn(true); // User ist eingeloggt

    // Echter SessionManager (State-based Testing)
    $session = new SessionManager(new SystemClock());

    /** @var ConfigInterface&\PHPUnit\Framework\MockObject\Stub $config */
    $config = $this->createStub(ConfigInterface::class);
    $config->method('getBaseUrl')->willReturn('https://app.local/');

    $middleware = new AuthMiddleware($session, $config, $auth);
    $request = new ServerRequest(server: ['REQUEST_URI' => '/admin']);

    $response = $middleware->process($request, fn () => new HtmlResponse('Admin Area'));

    expect($response)->toBeInstanceOf(HtmlResponse::class);
});

test('it redirects to login if user is not logged in', function (): void {
    /** @var AuthorizationInterface&\PHPUnit\Framework\MockObject\Stub $auth */
    $auth = $this->createStub(AuthorizationInterface::class);
    $auth->method('isLoggedIn')->willReturn(false); // User ist GAST

    $session = new SessionManager(new SystemClock());

    /** @var ConfigInterface&\PHPUnit\Framework\MockObject\Stub $config */
    $config = $this->createStub(ConfigInterface::class);
    $config->method('getBaseUrl')->willReturn('https://app.local/');

    $middleware = new AuthMiddleware($session, $config, $auth);

    // Simuliert einen Aufruf, bei dem ein Login-Code als GET-Parameter mitgeschleift wird
    $request = new ServerRequest(server: ['REQUEST_URI' => '/admin'], get: ['code' => 'XYZ']);

    // Die nächste Schicht darf nicht erreicht werden
    $next = fn () => throw new \Exception('Sollte nicht passieren!');

    /** @var RedirectResponse $response */
    $response = $middleware->process($request, $next);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->url)->toBe('https://app.local/admin_login?code=XYZ');
});

test('it routes history requests to history login if no history email is set', function (): void {
    $session = new SessionManager(new SystemClock());
    $session->clearHistoryEmail(); // Sicherstellen, dass kein Pächter eingeloggt ist

    /** @var ConfigInterface&\PHPUnit\Framework\MockObject\Stub $config */
    $config = $this->createStub(ConfigInterface::class);
    $config->method('getBaseUrl')->willReturn('https://app.local/');

    /** @var AuthorizationInterface&\PHPUnit\Framework\MockObject\Stub $auth */
    $auth = $this->createStub(AuthorizationInterface::class);

    $middleware = new AuthMiddleware($session, $config, $auth);
    $request = new ServerRequest(server: ['REQUEST_URI' => '/history']);

    $next = fn () => throw new \Exception('Sollte nicht passieren!');

    /** @var RedirectResponse $response */
    $response = $middleware->process($request, $next);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->url)->toBe('https://app.local/history_login');
});
