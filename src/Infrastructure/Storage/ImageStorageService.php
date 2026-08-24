<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\ImageStorageInterface;

final readonly class ImageStorageService implements ImageStorageInterface
{
    public function __construct(private ConfigInterface $config)
    {
    }

    public function getImageUrl(string $folder, string $id, string $fallback): string
    {
        // FIX: Wir zwingen hier den Slash an das Ende der BaseUrl
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';

        $physicalPath = \rtrim((string) $this->config->get('root_path', ''), '/\\') . '/public/assets/img/' . $folder . '/' . $id . '.webp';

        if (\file_exists($physicalPath)) {
            $mtime = \filemtime($physicalPath);

            return $baseUrl . 'assets/img/' . $folder . '/' . $id . '.webp?v=' . $mtime;
        }

        return $baseUrl . 'assets/img/icons/' . $fallback;
    }

    public function uploadImage(string $folder, string $id, array $file): bool
    {
        $targetDir = \rtrim((string) $this->config->get('root_path', ''), '/\\') . '/public/assets/img/' . $folder;
        if (!\is_dir($targetDir)) {
            \mkdir($targetDir, 0o755, true);
        }

        $targetPath = $targetDir . '/' . $id . '.webp';
        $tmpPath = $file['tmp_name'] ?? '';

        if ($tmpPath === '' || !\file_exists($tmpPath)) {
            return false;
        }

        $info = @\getimagesize($tmpPath);
        if ($info === false) {
            return false;
        }

        $mime = $info['mime'];
        $image = match ($mime) {
            'image/jpeg' => @\imagecreatefromjpeg($tmpPath),
            'image/png' => @\imagecreatefrompng($tmpPath),
            'image/webp' => @\imagecreatefromwebp($tmpPath),
            'image/gif' => @\imagecreatefromgif($tmpPath),
            default => null,
        };

        if (!$image) {
            return false;
        }

        $width = \imagesx($image);
        $height = \imagesy($image);
        $size = \min($width, $height);
        $cropX = (int) (($width - $size) / 2);
        $cropY = (int) (($height - $size) / 2);

        $square = \imagecreatetruecolor($size, $size);
        if ($square !== false) {
            if ($mime === 'image/png' || $mime === 'image/webp') {
                \imagealphablending($square, false);
                \imagesavealpha($square, true);
                $transparent = \imagecolorallocatealpha($square, 255, 255, 255, 127);
                \imagefilledrectangle($square, 0, 0, $size, $size, $transparent);
            }
            \imagecopyresampled($square, $image, 0, 0, $cropX, $cropY, $size, $size, $size, $size);
            \imagewebp($square, $targetPath, 85);
            \imagedestroy($square);
        }

        \imagedestroy($image);

        return \file_exists($targetPath);
    }
}
