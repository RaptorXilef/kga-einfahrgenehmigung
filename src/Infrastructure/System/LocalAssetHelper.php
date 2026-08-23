<?php

declare(strict_types=1);

namespace App\Infrastructure\System;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\AssetHelperInterface;

final class LocalAssetHelper implements AssetHelperInterface
{
    /**
     * RAM-Cache für den aktuellen Request, um Festplattenzugriffe (I/O) zu minimieren.
     * @var array<string, string>
     */
    private array $mtimeCache = [];

    public function __construct(private readonly ConfigInterface $config)
    {
    }

    public function url(string $assetPath): string
    {
        $assetPath = \ltrim($assetPath, '/');
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/');
        $fullUrl = $baseUrl . '/' . $assetPath;

        if (isset($this->mtimeCache[$assetPath])) {
            $mtime = $this->mtimeCache[$assetPath];

            return $mtime === '' ? $fullUrl : $fullUrl . '?v=' . $mtime;
        }

        $rootRaw = $this->config->get('root_path', '');
        $root = \is_string($rootRaw) ? $rootRaw : '';
        $publicDir = \rtrim($root, '/\\') . '/public';
        $physicalPath = $publicDir . '/' . $assetPath;

        \clearstatcache(true, $physicalPath);

        if (\file_exists($physicalPath)) {
            $mtime = (string) \filemtime($physicalPath);
            $this->mtimeCache[$assetPath] = $mtime;

            return $fullUrl . '?v=' . $mtime;
        }

        $this->mtimeCache[$assetPath] = '';

        return $fullUrl;
    }
}
