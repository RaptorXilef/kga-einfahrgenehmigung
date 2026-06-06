<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Service für automatische ZIP-Updates via GitHub.
 *
 * Lädt Releases herunter, wendet Whitelists an und schützt Konfigurations-
 * sowie Speicherdaten.
 *
 * Path: src/Core/Service/GitHubUpdaterService.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 */
final readonly class GitHubUpdaterService
{
    private const GITHUB_API_URL = 'https://api.github.com/repos/RaptorXilef/kga-einfahrgenehmigung';

    // Absolute Sperrzone! Diese Pfade werden beim Update NIEMALS angerührt.
    private const UPDATE_BLACKLIST = [
        'public/assets/img/user_images/',
        'public/assets/img/group_images/',
        // Schützt exakt die individuellen Vereinslogos des Nutzers
        'public/assets/img/logo/kga.webp',
        'public/assets/img/logo/kga.png',
        'public/assets/img/logo/kga.jpg',
        'public/assets/img/logo/kga.jpeg',
        // Die Datei kga-zm.webp steht absichtlich NICHT hier, damit sie geupdatet wird!
        'src/assets/',
        'config/', // config wird später separat gemerged
        'storage/',
    ];

    // Nur Dateien in diesen Pfaden (aus dem Root des ZIPs) dürfen ins Live-System kopiert werden!
    private const UPDATE_WHITELIST = [
        'public/',
        'src/Application/',
        'src/Bootstrap/',
        'src/Contracts/',
        'src/Core/',
        'src/Infrastructure/',
        'templates/',
        'vendor/', // Vendor kommt fertig aus der GitHub Action
        'CHANGELOG.md',
        'README.md',
        'composer.json',
        'package.json',
    ];

    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    /**
     * Prüft, ob ein neues Release auf GitHub verfügbar ist.
     *
     * @param string $currentVersion Die aktuell installierte Version (z.B. "v1.2.0")
     *
     * @return array|null Array mit Release-Daten oder null, wenn aktuell.
     */
    public function checkForUpdate(string $currentVersion): ?array
    {
        $response = $this->makeApiRequest('/releases/latest');

        if (! $response || ! isset($response['tag_name'])) {
            return null;
        }

        $latestVersion = $response['tag_name'];

        // Versionsnummern vergleichen (entfernt 'v' für sauberen Vergleich)
        $cleanCurrent = \ltrim($currentVersion, 'vV');
        $cleanLatest  = \ltrim($latestVersion, 'vV');

        if (\version_compare($cleanLatest, $cleanCurrent, '>')) {
            // Wir suchen in den angehängten Assets nach unserer gebauten ZIP
            $downloadUrl = '';
            if (isset($response['assets']) && \is_array($response['assets'])) {
                foreach ($response['assets'] as $asset) {
                    if ($asset['name'] === 'kga-update.zip') {
                        $downloadUrl = $asset['browser_download_url'];

                        break;
                    }
                }
            }

            // Fallback, falls die Action mal nicht gelaufen ist
            if ($downloadUrl === '') {
                $downloadUrl = $response['zipball_url'] ?? '';
            }

            return [
                'version'      => $latestVersion,
                'name'         => $response['name'] ?? $latestVersion,
                'notes'        => $response['body'] ?? '',
                'published_at' => $response['published_at'] ?? '',
                'zipball_url'  => $downloadUrl, // <--- Nimmt jetzt das fertige Asset!
            ];
        }

        // FIX: Hier fehlten der Return-Wert und die Klammer!
        return null;
    }

    /**
     * Führt das Update durch (Download, Entpacken, Whitelist anwenden, Cleanup).
     *
     * @param string $zipUrl Die URL zur ZIP-Datei des Releases.
     *
     * @return bool True bei Erfolg.
     */
    public function performUpdate(string $zipUrl): bool
    {
        $rootPath = \rtrim((string) $this->config->get('root_path'), '/\\');
        $tempDir  = $rootPath . '/storage/temp_update';
        $zipFile  = $tempDir . '/update.zip';

        // 1. Ordner vorbereiten
        if (! \is_dir($tempDir)) {
            \mkdir($tempDir, 0o755, true);
        }

        // 2. ZIP herunterladen
        if (! $this->downloadFile($zipUrl, $zipFile)) {
            $this->cleanup($tempDir);

            throw new \RuntimeException('Fehler beim Herunterladen des Updates.');
        }

        // 3. ZIP entpacken
        $zip = new \ZipArchive();
        if ($zip->open($zipFile) !== true) {
            $this->cleanup($tempDir);

            throw new \RuntimeException('Das Update-Archiv konnte nicht geöffnet werden.');
        }

        $extractPath = $tempDir . '/extracted';
        $zip->extractTo($extractPath);
        $zip->close();

        // 4. Den Hauptordner im ZIP finden (GitHub packt alles in einen Unterordner "Owner-Repo-CommitHash")
        // Hinweis: Wenn wir das Release-Asset laden, gibt es evtl. keinen Unterordner. Wir prüfen beides.
        $extractedFolders = \glob($extractPath . '/*', \GLOB_ONLYDIR);
        $sourceRoot       = $extractPath; // Default: Direkt im Root entpackt

        if (! empty($extractedFolders) && \count(\glob($extractPath . '/*')) === 1) {
            $sourceRoot = $extractedFolders[0]; // GitHub-Standard: Alles in einem Unterordner
        }

        // 5. Whitelist/Blacklist anwenden und Dateien kopieren
        $this->copyAllowedFiles($sourceRoot, $rootPath);

        // 6. Aufräumen
        $this->cleanup($tempDir);

        return true;
    }

    /**
     * Kopiert rekursiv alle Dateien, die der Whitelist entsprechen und nicht blockiert sind.
     */
    private function copyAllowedFiles(string $sourceDir, string $targetDir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }

            // Relativen Pfad (aus Sicht des Projekt-Roots) berechnen
            $relativePath = \str_replace($sourceDir . \DIRECTORY_SEPARATOR, '', $item->getPathname());
            $relativePath = \str_replace('\\', '/', $relativePath); // Für Windows

            // Prüfen, ob der Pfad erlaubt ist
            if ($this->isPathAllowed($relativePath)) {
                $targetFile    = $targetDir . '/' . $relativePath;
                $targetDirPath = \dirname($targetFile);

                if (! \is_dir($targetDirPath)) {
                    \mkdir($targetDirPath, 0o755, true);
                }

                \copy($item->getPathname(), $targetFile);
            }
        }
    }

    /**
     * Prüft, ob ein Dateipfad laut BLACKLIST & WHITELIST erlaubt ist.
     */
    private function isPathAllowed(string $path): bool
    {
        // 1. Blacklist blockiert strikt (Höchste Priorität)
        foreach (self::UPDATE_BLACKLIST as $blocked) {
            if (\str_starts_with($path, $blocked)) {
                return false;
            }
        }

        // 2. Whitelist erlaubt (Wenn nicht blockiert)
        foreach (self::UPDATE_WHITELIST as $allowed) {
            // Entweder es ist ein Ordner (endet auf /) und der Pfad beginnt damit
            if (\str_ends_with($allowed, '/') && \str_starts_with($path, $allowed)) {
                return true;
            }
            // Oder es ist eine exakte Datei (z.B. composer.json)
            if ($path === $allowed) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lädt eine Datei via cURL herunter.
     */
    private function downloadFile(string $url, string $saveTo): bool
    {
        $fp = \fopen($saveTo, 'w+');
        if ($fp === false) {
            return false;
        }

        $ch = \curl_init($url);
        \curl_setopt_array($ch, [
            \CURLOPT_FILE           => $fp,
            \CURLOPT_FOLLOWLOCATION => true, // Wichtig für GitHub (Redirects!)
            \CURLOPT_USERAGENT      => 'KGA-Updater-App',
            \CURLOPT_TIMEOUT        => 120, // 60s auf 120s erhöht wegen Vendor-Größe
        ]);

        \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        \curl_close($ch);
        \fclose($fp);

        return $httpCode === 200;
    }

    /**
     * Löscht ein Verzeichnis samt Inhalt.
     */
    private function cleanup(string $dir): void
    {
        if (! \is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $fileinfo) {
            $todo = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            $todo($fileinfo->getRealPath());
        }

        \rmdir($dir);
    }

    /**
     * Hilfsmethode für den JSON-API-Aufruf.
     */
    private function makeApiRequest(string $endpoint): ?array
    {
        $url = self::GITHUB_API_URL . $endpoint;

        $ch = \curl_init();
        \curl_setopt_array($ch, [
            \CURLOPT_URL            => $url,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_USERAGENT      => 'KGA-Updater-App',
            \CURLOPT_TIMEOUT        => 10,
        ]);

        $response = \curl_exec($ch);
        $httpCode = \curl_getinfo($ch, \CURLINFO_HTTP_CODE);
        \curl_close($ch);

        if ($httpCode !== 200 || ! $response) {
            return null;
        }

        return \json_decode($response, true);
    }
}
