<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\RouteCacheInterface;

final readonly class FileRouteCache implements RouteCacheInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    private function getCacheFilePath(): string
    {
        $rootPath = \rtrim((string) $this->config->get('root_path', ''), '/\\');

        return $rootPath . '/cache/routes_v2.php';
    }

    private function getOldCacheFilePath(): string
    {
        $rootPath = \rtrim((string) $this->config->get('root_path', ''), '/\\');

        return $rootPath . '/cache/routes.php';
    }

    public function load(): ?array
    {
        $cacheFile = $this->getCacheFilePath();
        if (\file_exists($cacheFile)) {
            /** @var array{exact: array<string, array<string, array{class: string, auth: bool}>>, dynamic: array<string, array<string, array{class: string, auth: bool}>>} $routes */
            $routes = require $cacheFile;

            return $routes;
        }

        return null;
    }

    public function save(array $routes): void
    {
        $cacheFile = $this->getCacheFilePath();
        $cacheDir = \dirname($cacheFile);

        if (!\is_dir($cacheDir)) {
            \mkdir($cacheDir, 0o755, true);
        }

        \file_put_contents($cacheFile, '<?php return ' . \var_export($routes, true) . ';', \LOCK_EX);
    }

    public function clearOld(): void
    {
        $oldCache = $this->getOldCacheFilePath();
        if (\file_exists($oldCache)) {
            @\unlink($oldCache);
        }
    }

    public function clearAll(): void
    {
        $this->clearOld();
        $cacheFileV2 = $this->getCacheFilePath();
        if (\file_exists($cacheFileV2)) {
            @\unlink($cacheFileV2);
        }
    }
}
