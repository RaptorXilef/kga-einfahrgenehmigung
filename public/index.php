<?php

declare(strict_types=1);

use App\Application\Contracts\ResponseInterface;
use App\Application\FrontendController;
use App\Application\Http\ServerRequest;
use App\Bootstrap\Container;

$container = require_once __DIR__ . '/../src/Bootstrap/app.php';
\assert($container instanceof Container);

// ServerRequest muss auf das neue Format aus TwoKinds (mit Cookies) reagieren.
// Falls deine Klasse Cookies noch nicht unterstützt, können wir das gleich nachrüsten.
$req = new ServerRequest($_GET, $_POST, $_FILES, $_SERVER, [], $_COOKIE ?? []);

// VSA FIX: Binde den Request in den Container, damit Middlewares und der TemplateRenderer
// ihn via Dependency Injection erhalten können, ohne auf $_SERVER zugreifen zu müssen!
$container->bind(ServerRequest::class, fn () => $req);

$controller = $container->get(FrontendController::class);
\assert($controller instanceof FrontendController);

$response = $controller->handleRequest($req);
if ($response instanceof ResponseInterface) {
    $response->send();
}
