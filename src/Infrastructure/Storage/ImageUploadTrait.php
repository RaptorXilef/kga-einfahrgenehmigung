<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

/**
 * Trait für das Hochladen, Skalieren und Konvertieren von Bildern.
 * Bietet wiederverwendbare Methoden zur transparenten Umwandlung von
 * Profil- und Gruppenbildern in das speichereffiziente WebP-Format.
 *
 * Path: src/Infrastructure/Storage/ImageUploadTrait.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
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
        $safeId     = \basename($id); // Verhindert Path Traversal (../)
        $targetDir  = \rtrim($rootPath, '/\\') . '/public/assets/img/' . $folder . '/';
        $outputPath = $targetDir . $safeId . '.webp'; // Nutze $safeId

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

        if (! \extension_loaded('gd')) {
            return \move_uploaded_file($file['tmp_name'], $outputPath);
        }

        // [x] Sortiert
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

    /**
     * Generiert eine Cache-busting URL für ein hochgeladenes Bild.
     *
     * @param string $folder       Zielordner (z.B. 'user_images').
     * @param string $id           ID des Objekts.
     * @param string $fallbackIcon Name des Fallback-Icons.
     * @param string $rootPath     Root-Pfad der Anwendung.
     * @param string $baseUrl      Basis-URL der Anwendung.
     *
     * @return string Cache-gebustete URL.
     */
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
