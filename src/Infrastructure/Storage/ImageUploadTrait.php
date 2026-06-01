<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

// TODO DocBlock
trait ImageUploadTrait
{
    /**
     * Verarbeitet Bild-Uploads, konvertiert sie in das WebP-Format und skaliert sie transparent via GD.
     * Unterstützt JPEG, PNG, GIF und native WebP-Quellen. Sichert Kompatibilität durch Raw-Move bei
     * fehlender GD-Erweiterung.
     *
     * @param string               $folder 'user' oder 'group' zur Verzeichnissteuerung.
     * @param string               $id     Die ID des Ziel-Objekts (wird zum Dateinamen).
     * @param array<string, mixed> $file   Das native $_FILES['avatar'] Upload-Array.
     *
     * @return bool True bei erfolgreicher Konvertierung und Speicherung auf dem Datenträger.
     */
    protected function doUploadImage(string $folder, string $id, array $file, string $rootPath): bool
    {
        $targetDir  = \rtrim($rootPath, '/\\') . '/public/assets/img/' . $folder . '/';
        $outputPath = $targetDir . $id . '.webp';

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
            \IMAGETYPE_JPEG => @\imagecreatefromjpeg($file['tmp_name']),
            \IMAGETYPE_PNG  => @\imagecreatefrompng($file['tmp_name']),
            \IMAGETYPE_GIF  => @\imagecreatefromgif($file['tmp_name']),
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

    // TODO DocBlock
    protected function doGetImageUrl(string $folder, string $id, string $fallbackIcon, string $rootPath, string $baseUrl): string
    {
        $serverPath  = \rtrim($rootPath, '/\\') . '/public/assets/img/' . $folder . '/' . $id . '.webp';
        $browserPath = 'assets/img/' . $folder . '/' . $id . '.webp';

        if (\file_exists($serverPath)) {
            return $baseUrl . $browserPath . '?v=' . \filemtime($serverPath);
        }

        return $baseUrl . 'assets/img/icons/' . $fallbackIcon;
    }
}
