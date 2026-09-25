<?php

declare(strict_types=1);

namespace App\Application\Routing;

use App\Application\Attribute\RequiresAuth;
use App\Application\Attribute\Route;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\RouteCacheInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;

final class ActionRegistry
{
    /**
     * @var array{
     *   exact: array<string, array<string, array{class: string, auth: bool}>>,
     *   dynamic: array<string, array<string, array{class: string, auth: bool}>>
     * }
     */
    private array $routes = ['exact' => [], 'dynamic' => []];

    public function __construct(
        private readonly ConfigInterface $config,
        private readonly RouteCacheInterface $cache,
    ) {
        $this->loadRoutes();
    }

    private function loadRoutes(): void
    {
        $this->cache->clearOld();

        if (!$this->config->getBool('debug_mode', false)) {
            $cached = $this->cache->load();
            if (\is_array($cached)) {
                $this->routes = $cached;

                return;
            }
        }

        $rootPath = \rtrim($this->config->getString('root_path', ''), '/\\');

        // Scannt ab sofort NUR noch die VSA Modules (Legacy Actions wurden stranguliert)
        $this->scanDirectoryRecursively($rootPath . \DIRECTORY_SEPARATOR . 'src' . \DIRECTORY_SEPARATOR . 'Modules');

        $this->cache->save($this->routes);
    }

    private function scanDirectoryRecursively(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        $srcPath = \rtrim($this->config->getString('root_path', ''), '/\\') . \DIRECTORY_SEPARATOR . 'src';

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            // Performance: Wir prüfen NUR auf Dateien, die auf "Action.php" enden!
            if (!\str_ends_with($file->getFilename(), 'Action.php')) {
                continue;
            }

            $pathName = $file->getPathname();
            $relativePath = \str_replace($srcPath . \DIRECTORY_SEPARATOR, '', $pathName);
            $classSuffix = \str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $relativePath);
            $className = 'App\\' . $classSuffix;

            if (!\class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $requiresAuth = $reflection->getAttributes(RequiresAuth::class) !== [];

            foreach ($reflection->getAttributes(Route::class) as $attribute) {
                $route = $attribute->newInstance();
                $this->registerRoute($route->method, $route->path, $className, $requiresAuth);
            }
        }
    }

    private function registerRoute(string $method, string $path, string $className, bool $requiresAuth): void
    {
        if (\str_contains($path, '{')) {
            $replaced = \preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[^/]+)', $path);
            $regex = \is_string($replaced) ? $replaced : '';
            $this->routes['dynamic'][$method]['#^' . $regex . '$#'] = ['class' => $className, 'auth' => $requiresAuth];

            return;
        }

        $this->routes['exact'][$method][$path] = ['class' => $className, 'auth' => $requiresAuth];
    }

    /**
     * @return array{class: string, params: array<string, string>, requiresAuth: bool}|null
     */
    public function match(string $method, string $path): ?array
    {
        if (isset($this->routes['exact'][$method][$path])) {
            $routeData = $this->routes['exact'][$method][$path];

            return [
                'class' => $routeData['class'],
                'params' => [],
                'requiresAuth' => $routeData['auth'],
            ];
        }

        $dynamics = $this->routes['dynamic'][$method] ?? [];
        foreach ($dynamics as $regex => $routeData) {
            if (\preg_match($regex, $path, $matches) === 1) {
                $params = [];
                foreach ($matches as $k => $v) {
                    if (!\is_string($k)) {
                        continue;
                    }
                    $params[$k] = $v;
                }

                return [
                    'class' => $routeData['class'],
                    'params' => $params,
                    'requiresAuth' => $routeData['auth'],
                ];
            }
        }

        return null;
    }
}
