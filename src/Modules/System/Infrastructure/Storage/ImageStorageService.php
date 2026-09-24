<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\ImageStorageInterface;
use Override;

final readonly class ImageStorageService implements ImageStorageInterface
{
    public function __construct(private ConfigInterface $config)
    {
    }

    #[Override]
    public function uploadImage(string $folder, string $id, array $file): bool
    {
        if (!isset($file['error']) || $file['error'] !== \UPLOAD_ERR_OK) {
            return false;
        }

        $tmpPath = $file['tmp_name'];
        $info = \getimagesize($tmpPath);
        if (!$info) {
            return false;
        }

        $mime = $info['mime'];
        $source = match ($mime) {
            'image/jpeg' => \imagecreatefromjpeg($tmpPath),
            'image/png' => \imagecreatefrompng($tmpPath),
            'image/webp' => \imagecreatefromwebp($tmpPath),
            'image/gif' => \imagecreatefromgif($tmpPath),
            default => null,
        };

        if (!$source) {
            return false;
        }

        // Schneide das Bild quadratisch zu (zentriert)
        $width = \imagesx($source);
        $height = \imagesy($source);
        $size = \min($width, $height);
        $x = (int) (($width - $size) / 2);
        $y = (int) (($height - $size) / 2);

        $target = \imagecreatetruecolor(250, 250);

        \imagealphablending($target, false);
        \imagesavealpha($target, true);
        $transparent = \imagecolorallocatealpha($target, 255, 255, 255, 127);
        \imagefill($target, 0, 0, $transparent);

        \imagecopyresampled($target, $source, 0, 0, $x, $y, 250, 250, $size, $size);

        $root = \rtrim((string) $this->config->get('root_path'), '/\\');
        $destPath = $root . '/public/assets/img/' . $folder . '/' . $id . '.webp';

        return \imagewebp($target, $destPath, 85);
    }

    #[Override]
    public function getImageUrl(string $folder, string $id, string $fallbackIcon): string
    {
        $root = \rtrim((string) $this->config->get('root_path'), '/\\');
        $serverPath = $root . '/public/assets/img/' . $folder . '/' . $id . '.webp';
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';

        if (\file_exists($serverPath)) {
            return $baseUrl . 'assets/img/' . $folder . '/' . $id . '.webp?v=' . \filemtime($serverPath);
        }

        return $baseUrl . 'assets/img/icons/' . $fallbackIcon;
    }

    #[Override]
    public function deleteImage(string $folder, string $id): void
    {
        $root = \rtrim((string) $this->config->get('root_path'), '/\\');
        $serverPath = $root . '/public/assets/img/' . $folder . '/' . $id . '.webp';

        if (!\file_exists($serverPath)) {
            return;
        }

        @\unlink($serverPath);
    }
}
