<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\ImageStorageInterface;

/**
 * Verantwortlich für das Speichern, Skalieren und Laden von Bildern.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ImageStorageService implements ImageStorageInterface
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    public function uploadImage(string $folder, string $id, array $file): bool
    {
        if (\str_contains($id, '://') || \str_contains($id, "\0") || \str_contains($id, '..')) {
            return false;
        }

        $safeId     = \basename($id);
        $rootPath   = (string) $this->config->get('root_path');
        $targetDir  = \rtrim($rootPath, '/\\') . '/public/assets/img/' . $folder . '/';
        $outputPath = $targetDir . $safeId . '.webp';

        if (! \is_dir($targetDir)) {
            \mkdir($targetDir, 0o755, true);
        }

        if (! \extension_loaded('gd')) {
            return \move_uploaded_file($file['tmp_name'], $outputPath);
        }

        $info = @\getimagesize($file['tmp_name']);
        if (! $info) {
            return false;
        }

        $src = match ($info[2]) {
            \IMAGETYPE_GIF  => @\imagecreatefromgif($file['tmp_name']),
            \IMAGETYPE_JPEG => @\imagecreatefromjpeg($file['tmp_name']),
            \IMAGETYPE_PNG  => @\imagecreatefrompng($file['tmp_name']),
            \IMAGETYPE_WEBP => @\imagecreatefromwebp($file['tmp_name']),
            default         => null
        };

        if (! $src) {
            return false;
        }

        $width  = \imagesx($src);
        $height = \imagesy($src);
        $dst    = \imagecreatetruecolor($width, $height);

        \imagealphablending($dst, false);
        \imagesavealpha($dst, true);
        $transparent = \imagecolorallocatealpha($dst, 255, 255, 255, 127);
        \imagefill($dst, 0, 0, $transparent);
        \imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $width, $height);

        return \imagewebp($dst, $outputPath, 75);
    }

    public function getImageUrl(string $folder, string $id, string $fallbackIcon): string
    {
        $baseUrl = $this->config->getBaseUrl();

        if (\str_contains($id, '://') || \str_contains($id, "\0") || \str_contains($id, '..')) {
            return $baseUrl . 'assets/img/icons/' . $fallbackIcon;
        }

        $rootPath    = (string) $this->config->get('root_path');
        $serverPath  = \rtrim($rootPath, '/\\') . '/public/assets/img/' . $folder . '/' . $id . '.webp';
        $browserPath = 'assets/img/' . $folder . '/' . $id . '.webp';

        if (\file_exists($serverPath)) {
            return $baseUrl . $browserPath . '?v=' . \filemtime($serverPath);
        }

        return $baseUrl . 'assets/img/icons/' . $fallbackIcon;
    }
}
