<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Contracts\ActionInterface;
use App\Application\Contracts\RequiresPermissionInterface;
use App\Application\Contracts\ResponseInterface;
use App\Application\Contracts\ViewActionInterface;
use App\Application\Http\ServerRequest;
use App\Application\Middleware\ApiCsrfMiddleware;
use App\Application\Middleware\AuthMiddleware;
use App\Application\Middleware\CsrfMiddleware;
use App\Application\Middleware\FormExceptionHandlerMiddleware;
use App\Application\Middleware\JsonBodyParserMiddleware;
use App\Application\Middleware\MaintenanceModeMiddleware;
use App\Application\Middleware\MiddlewarePipeline;
use App\Application\Middleware\SecurityHeadersMiddleware;
use App\Application\Response\HtmlResponse;
use App\Application\Response\RedirectResponse;
use App\Application\Routing\UniversalActionFactory;
use App\Application\Session\SessionManager;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthorizationInterface;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
final readonly class FrontendController
{
    public function __construct(
        private ConfigInterface $config,
        private UniversalActionFactory $actionFactory,
        private SecurityHeadersMiddleware $securityHeaders,
        private SessionManager $sessionManager,
        private AuthorizationInterface $authService,
        private JsonBodyParserMiddleware $jsonBodyParser,
        private ApiCsrfMiddleware $apiCsrf,
        private FormExceptionHandlerMiddleware $formExceptionHandler,
        private MaintenanceModeMiddleware $maintenanceMode, // VSA FIX: Maintenance Logic decoupled
    ) {
    }

    public function handleRequest(ServerRequest $request): ?ResponseInterface
    {
        $relativePath = $this->resolveRelativePath($request);
        $routeMatch = $this->resolveRoute($request, $relativePath);

        $request = $routeMatch['request'];
        $className = $routeMatch['class'];
        $requiresAuth = $routeMatch['requiresAuth'];

        return $this->executePipeline($request, $className, $requiresAuth, $relativePath);
    }

    private function resolveRelativePath(ServerRequest $request): string
    {
        $pathRaw = \parse_url($request->getPath(), \PHP_URL_PATH);
        $path = \is_string($pathRaw) ? $pathRaw : '/';

        $basePathRaw = \parse_url($this->config->getBaseUrl(), \PHP_URL_PATH);
        $basePath = \is_string($basePathRaw) ? $basePathRaw : '/';

        $relativePath = '/' . \ltrim($path, '/');

        // Wenn die App in einem Unterordner läuft
        if (\str_starts_with($path, $basePath)) {
            $relativePath = '/' . \ltrim(\substr($path, \strlen($basePath)), '/');
        }

        // KGA Legacy Support: public/admin.php -> /admin
        if (\str_ends_with($relativePath, '.php')) {
            $relativePath = \substr($relativePath, 0, -4);
        }

        if ($relativePath === '/index') {
            return '/';
        }

        return $relativePath;
    }

    /**
     * @return array{request: ServerRequest, class: string, requiresAuth: bool}
     */
    private function resolveRoute(ServerRequest $request, string $relativePath): array
    {
        $method = $request->getMethod();
        $matched = $this->actionFactory->getRegistry()->match($method, $relativePath);

        // Fallback, wenn Route nicht gefunden
        if ($matched === null) {
            return ['request' => $request, 'class' => '', 'requiresAuth' => false];
        }

        if (\is_array($matched)) {
            $className = \is_string($matched['class']) ? $matched['class'] : '';
            $params = \is_array($matched['params']) ? $matched['params'] : [];

            return [
                'request' => $request->withInput(\array_merge($request->input, $params)),
                'class' => $className,
                'requiresAuth' => ($matched['requiresAuth'] ?? false) === true,
            ];
        }

        return ['request' => $request, 'class' => '', 'requiresAuth' => false];
    }

    private function executePipeline(ServerRequest $request, string $className, bool $requiresAuth, string $path): ?ResponseInterface
    {
        $pipeline = new MiddlewarePipeline();

        $pipeline->add($this->securityHeaders);
        $pipeline->add($this->jsonBodyParser);
        $pipeline->add($this->formExceptionHandler);

        // Neu: Wartungsmodus-Prüfung geschieht jetzt in der Middleware-Kette
        $pipeline->add($this->maintenanceMode);

        // Ausnahmen für Server-to-Server oder Cronjobs, die keine Session (und somit kein CSRF-Token) besitzen
        $isCronOrWebhook = \str_starts_with($path, '/api/cron/')
            || \str_starts_with($path, '/api/system_update')
            || \str_starts_with($path, '/api/process_mail_queue');

        if (!$isCronOrWebhook) {
            if (\str_starts_with($path, '/api/')) {
                $pipeline->add($this->apiCsrf);
            } else {
                $pipeline->add(new CsrfMiddleware($this->sessionManager, \rtrim($this->config->getBaseUrl(), '/') . '/'));
            }
        }

        if ($requiresAuth) {
            $pipeline->add(new AuthMiddleware($this->sessionManager, $this->config));
        }

        $response = $pipeline->process($request, function (ServerRequest $req) use ($className): mixed {
            $action = $this->actionFactory->create($className);

            // --- SECURITY FIX: Role-Based Access Control (RBAC) Enforcement ---
            if ($action instanceof RequiresPermissionInterface) {
                if (!$this->authService->hasPermission($action->getRequiredPermission())) {
                    $this->sessionManager->addFlash('error', 'Zugriff verweigert: Sie haben nicht die erforderlichen Berechtigungen für diese Aktion.');

                    return new RedirectResponse(\rtrim($this->config->getBaseUrl(), '/') . '/admin');
                }
            }

            if ($action instanceof ActionInterface || $action instanceof ViewActionInterface) {
                return $action->execute($req);
            }

            return new HtmlResponse('404 Not Found - Die angeforderte Seite existiert nicht.', 404);
        });

        if ($response instanceof ResponseInterface) {
            return $response;
        }

        return null;
    }
}
