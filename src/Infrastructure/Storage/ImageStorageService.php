<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\ImageStorageInterface;

/**
 * Service für die Verarbeitung und Speicherung von Profil- und Rollenbildern.
 * Konvertiert hochgeladene Bilder performant und speicherplatzsparend in das WebP Format.
 */
final readonly class ImageStorageService implements ImageStorageInterface
{
    public function __construct(private ConfigInterface $config)
    {
    }

    /**
     * Lädt ein Bild hoch, schneidet es quadratisch zu und speichert es als WebP.
     */
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

        // Erstelle eine 250x250 Ziel-Leinwand
        $target = \imagecreatetruecolor(250, 250);

        // Transparenz für PNGs und WebP erhalten
        \imagealphablending($target, false);
        \imagesavealpha($target, true);
        $transparent = \imagecolorallocatealpha($target, 255, 255, 255, 127);
        \imagefill($target, 0, 0, $transparent);

        // Bild resampeln
        \imagecopyresampled($target, $source, 0, 0, $x, $y, 250, 250, $size, $size);

        $root = \rtrim((string) $this->config->get('root_path'), '/\\');
        $destPath = $root . '/public/assets/img/' . $folder . '/' . $id . '.webp';

        // Speichern als WebP mit 85% Qualität
        $result = \imagewebp($target, $destPath, 85);

        // WICHTIG: Kein imagedestroy() mehr!
        // Ab PHP 8.0 sind GD-Ressourcen vollwertige Objekte (\GdImage).
        // Der PHP Garbage Collector räumt sie am Ende des Skripts automatisch aus dem Speicher.
        // Ab PHP 8.5 ist der Aufruf von imagedestroy() offiziell verboten und wirft Exceptions.

        return $result;
    }

    /**
     * Ermittelt die URL zu einem gespeicherten Bild.
     * Fügt einen Cache-Buster (filemtime) hinzu, damit Browser nach einem
     * Upload das alte Bild nicht aus dem Cache laden.
     */
    public function getImageUrl(string $folder, string $id, string $fallbackIcon): string
    {
        $root = \rtrim((string) $this->config->get('root_path'), '/\\');
        $serverPath = $root . '/public/assets/img/' . $folder . '/' . $id . '.webp';
        $baseUrl = \rtrim($this->config->getBaseUrl(), '/') . '/';

        if (\file_exists($serverPath)) {
            // Cache-Busting via Änderungsdatum der Datei
            return $baseUrl . 'assets/img/' . $folder . '/' . $id . '.webp?v=' . \filemtime($serverPath);
        }

        return $baseUrl . 'assets/img/icons/' . $fallbackIcon;
    }
}
