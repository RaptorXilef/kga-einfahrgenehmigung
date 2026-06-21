<?php

declare(strict_types=1);

namespace App\Infrastructure\Maintenance;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Utils\ClockInterface;
use App\Infrastructure\Storage\JsonHelper;
use App\Infrastructure\Storage\SafeJsonWriterTrait;

/**
 * Service für automatische ZIP-Updates via GitHub.
 *
 * Lädt Releases herunter, wendet Whitelists an und schützt Konfigurations-
 * sowie Speicherdaten.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class GitHubUpdaterService
{
    use SafeJsonWriterTrait;

    // TODO URL
    private const GITHUB_API_URL = 'https://api.github.com/repos/RaptorXilef/kga-einfahrgenehmigung';

    // Fallback-Regeln, falls die update_manifest.json im ZIP mal fehlen sollte
    // Absolute Sperrzone! Diese Pfade werden beim Update NIEMALS angerührt.
    private const DEFAULT_BLACKLIST = [
        'public/assets/img/user_images/',
        'public/assets/img/group_images/',
        // Schützt exakt die individuellen Vereinslogos des Nutzers
        'public/assets/img/logo/kga.webp',
        'public/assets/img/logo/kga.png',
        'public/assets/img/logo/kga.jpg',
        'public/assets/img/logo/kga.jpeg',
        'public/assets/documents/',
        // Die Datei kga-zm.webp steht absichtlich NICHT hier, damit sie geupdatet wird!
        'src/assets/',
        'storage/',
    ];

    // Nur Dateien in diesen Pfaden (aus dem Root des ZIPs) dürfen ins Live-System kopiert werden!
    private const DEFAULT_WHITELIST = [
        'config/', // Erlaubt das Überschreiben der Standard-Configs (z.B. email.php)
        'public/',
        'src/Application/',
        'src/Bootstrap/',
        'src/Contracts/',
        'src/Core/',
        'src/Infrastructure/',
        'templates/',
        'vendor/',
        'CHANGELOG.md',
        'README.md',
        'composer.json',
        'package.json',
        'update_manifest.json',
    ];

    // Fallback für kritische Core-Configs, falls das Manifest fehlt
    private const DEFAULT_CORE_CONFIGS = [
        'config/sql_schema.php',
        'config/permissions.php',
        'config/.htaccess',
    ];

    public function __construct(
        private ClockInterface $clock,
        private ConfigInterface $config,
    ) {
    }

    // --- Public Update Lifecycle API ---

    /**
     * Schritt 1: Prüfen
     *
     * Prüft, ob ein neues Release auf GitHub verfügbar ist.
     *
     * @param string $currentVersion Die aktuell installierte Version (z.B. "v1.2.0")
     *
     * @return array|null Array mit Release-Daten oder null, wenn aktuell.
     */
    public function checkForUpdate(string $currentVersion, bool $force = false): ?array
    {
        // NEU: Wenn wir uns in einer lokalen Testumgebung befinden, cURL-Anfrage überspringen
        if ($this->config->get('is_local_env', false)) {
            return null;
        }

        $logDir = $this->config->getStoragePath('logs');
        if (! \is_dir($logDir)) {
            \mkdir($logDir, 0o755, true);
        }

        $cacheFile = $logDir . '/latest_release.json';
        $now       = $this->clock->now()->getTimestamp();

        // 1. Aus Cache lesen, wenn nicht erzwungen und jünger als 24 Stunden
        if (! $force && \file_exists($cacheFile)) {
            if (($now - \filemtime($cacheFile)) < 86400) { // 86400 Sekunden = 24h
                $cachedResponse = JsonHelper::read($cacheFile);
                if (! empty($cachedResponse)) {
                    return $this->compareAndFormatRelease($cachedResponse, $currentVersion);
                }
            }
        }

        // 2. Live von GitHub abrufen
        $response = $this->makeApiRequest('/releases/latest');

        if (! $response || ! isset($response['tag_name'])) {
            return null;
        }

        // 3. Im Cache speichern
        $this->writeJsonSafely($cacheFile, $response);

        return $this->compareAndFormatRelease($response, $currentVersion);
    }

    private function compareAndFormatRelease(array $response, string $currentVersion): ?array
    {
        $latestVersion = $response['tag_name'];

        // Versionsnummern vergleichen (entfernt 'v' für sauberen Vergleich)
        $cleanCurrent = \ltrim($currentVersion, 'vV');
        $cleanLatest  = \ltrim($latestVersion, 'vV');

        if (\version_compare($cleanLatest, $cleanCurrent, '>')) {
            // Wir suchen in den angehängten Assets nach unserer gebauten ZIP
            $downloadUrl = '';

            // Wir suchen jetzt dynamisch nach dem Update-Paket basierend auf dem aktuellen Tag
            // Der Name ist jetzt: kga-einfahrts-manager-update-{latestVersion}.zip
            $expectedFilename = 'kga-einfahrts-manager-update-' . $latestVersion . '.zip';

            if (isset($response['assets']) && \is_array($response['assets'])) {
                foreach ($response['assets'] as $asset) {
                    if ($asset['name'] === $expectedFilename) {
                        $downloadUrl = $asset['browser_download_url'];

                        break;
                    }
                }
            }

            // Fallback bleibt, falls die Namenskonvention mal nicht matcht
            if ($downloadUrl === '') {
                return null; // Oder Fallback auf zipball_url
            }

            return [
                'version'      => $latestVersion,
                'name'         => $response['name'] ?? $latestVersion,
                'notes'        => $response['body'] ?? '',
                'published_at' => $response['published_at'] ?? '',
                'zipball_url'  => $downloadUrl,
            ];
        }

        return null;
    }

    /**
     * Schritt 2: Ausführen
     *
     * Führt das Update durch (Download, Entpacken, Whitelist anwenden, Cleanup).
     *
     * @param string $zipUrl Die URL zur ZIP-Datei des Releases.
     *
     * @return bool True bei Erfolg.
     */
    public function performUpdate(string $zipUrl): bool
    {
        // Lokale Installationen blockieren, um cURL-Fehler abzufangen
        if ($this->config->get('is_local_env', false)) {
            throw new \RuntimeException('GitHub-Updates sind in der lokalen Testumgebung deaktiviert.');
        }

        $rootPath = \rtrim((string) $this->config->get('root_path'), '/\\');
        $tempDir  = $this->config->getStoragePath('temp_update');
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

        // Schutz vor ZIP-Bomben (Decompression Bomb / DoS-Attacken)
        // Prüfe die dekomprimierte Gesamtgröße VOR dem eigentlichen Entpacken
        $totalUncompressedSize = 0;
        $maxAllowedSize        = 10 * 1024 * 1024; // Limit auf 10 MB (ca. 5x größer als aktueller Bedarf)

        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $stat = $zip->statIndex($i);
            if ($stat !== false) {
                $totalUncompressedSize += $stat['size'];
            }
        }

        if ($totalUncompressedSize > $maxAllowedSize) {
            $zip->close();
            $this->cleanup($tempDir);

            throw new \RuntimeException('Sicherheitsabbruch: Das Update-Archiv überschreitet das Größenlimit.');
        }

        $extractPath = $tempDir . '/extracted';

        // Entpacken auf Fehler prüfen und Notbremse ziehen!
        if (! $zip->extractTo($extractPath)) {
            $zip->close();
            $this->cleanup($tempDir);

            throw new \RuntimeException('Das Update-Archiv konnte nicht entpackt werden.');
        }
        $zip->close();

        // 4. Den Hauptordner im ZIP finden
        // FIX: Sichere Erkennung des Quell-Ordners!
        $sourceFolder   = $extractPath;
        $extractedItems = \array_values(\array_diff(\scandir($extractPath), ['.', '..']));

        // Wenn genau 1 Element existiert UND es ein Ordner ist, dann ist das der GitHub-Source-Wrapper!
        if (\count($extractedItems) === 1 && \is_dir($extractPath . '/' . $extractedItems[0])) {
            $sourceFolder = $extractPath . '/' . $extractedItems[0];
        }

        // DYNAMISCHES MANIFEST LADEN
        $whitelist   = self::DEFAULT_WHITELIST;
        $blacklist   = self::DEFAULT_BLACKLIST;
        $coreConfigs = self::DEFAULT_CORE_CONFIGS;

        $manifestPath = $sourceFolder . '/update_manifest.json';
        if (\file_exists($manifestPath)) {
            try {
                $manifestData = JsonHelper::read($manifestPath);
                $whitelist    = $manifestData['whitelist'] ?? $whitelist;
                $blacklist    = $manifestData['blacklist'] ?? $blacklist;
                $coreConfigs  = $manifestData['core_configs'] ?? $coreConfigs;
            } catch (\Exception) {
                // Bei fehlerhaftem JSON auf Defaults zurückfallen
            }
        }

        // 1. NEUE & GEÄNDERTE DATEIEN KOPIEREN
        $this->copyAllowedFiles($sourceFolder, $rootPath, $whitelist, $blacklist, $coreConfigs);

        // 2. ORPHANED FILES (DATENMÜLL) LÖSCHEN (Wir übergeben die dynamische Blacklist als Schutz!)
        $this->purgeOrphanedFiles($rootPath, $sourceFolder, $blacklist);

        $this->cleanup($tempDir);

        return true;
    }

    // / --- Internal File Processing (Private) ---

    /**
     * Arbeitet performUpdate zu
     *
     * Kopiert rekursiv alle Dateien, die der Whitelist entsprechen und nicht blockiert sind.
     */
    private function copyAllowedFiles(string $sourceDir, string $targetDir, array $whitelist, array $blacklist, array $coreConfigs): void
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
            // FIX: Strikte String-Längen-Berechnung verhindert falsche Ersetzungen
            $relativePath = \substr($item->getPathname(), \strlen($sourceDir) + 1);
            $relativePath = \str_replace('\\', '/', $relativePath);

            // Prüfen, ob der Pfad erlaubt ist
            if ($this->isPathAllowed($relativePath, $whitelist, $blacklist, $coreConfigs)) {
                $targetFile    = $targetDir . '/' . $relativePath;
                $targetDirPath = \dirname($targetFile);

                if (! \is_dir($targetDirPath)) {
                    \mkdir($targetDirPath, 0o755, true);
                }

                \copy($item->getPathname(), $targetFile);
            }
        }
    }

    private function purgeOrphanedFiles(string $targetRoot, string $sourceRoot, array $blacklist): void
    {
        // public/ ist jetzt enthalten, aber durch isProtectedPath abgesichert!
        $directoriesToClean = ['src', 'templates', 'public', 'config'];

        foreach ($directoriesToClean as $dir) {
            $targetDir = $targetRoot . '/' . $dir;
            if (! \is_dir($targetDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($targetDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST, // Wichtig für rekursives rmdir
            );

            foreach ($iterator as $item) {
                // FIX: Strikte String-Längen-Berechnung
                $relativePath = \substr($item->getPathname(), \strlen($targetRoot) + 1);
                $relativePath = \str_replace('\\', '/', $relativePath);

                // Benutzer-Uploads und Storage komplett ignorieren (Die Blacklist definiert unsere geschützten Daten!)
                if ($this->isProtectedPath($relativePath, $blacklist)) {
                    continue;
                }

                $sourceEquivalent = $sourceRoot . '/' . $relativePath;

                if ($item->isFile()) {
                    // Konfigurations-Dateien: Niemals löschen, es sei denn es ist eine ".default.php" Datei!
                    if (\str_starts_with($relativePath, 'config/')) {
                        if (! \str_ends_with($relativePath, '.default.php')) {
                            continue;
                        }
                    }

                    // Existiert die Datei im Update-Paket nicht mehr? -> Löschen!
                    if (! \file_exists($sourceEquivalent)) {
                        @\unlink($item->getPathname());
                    }
                } elseif ($item->isDir()) {
                    // Leere verwaiste Ordner löschen
                    if (! \file_exists($sourceEquivalent)) {
                        @\rmdir($item->getPathname());
                    }
                }
            }
        }
    }

    /**
     * Sicherheits-Gatekeeper für Kopierprozess
     *
     * Prüft, ob ein Dateipfad laut BLACKLIST & WHITELIST erlaubt ist.
     */
    private function isPathAllowed(string $path, array $whitelist, array $blacklist, array $coreConfigs): bool
    {
        // 1. Blacklist blockiert strikt
        foreach ($blacklist as $blocked) {
            if (\str_starts_with($path, $blocked)) {
                return false;
            }
        }

        // 2. SPEZIALREGEL FÜR DEN CONFIG-ORDNER
        if (\str_starts_with($path, 'config/')) {
            if (\str_ends_with($path, '.default.php')) {
                return true;
            }

            // B) Explizite Core-Dateien erlauben (Dynamisch aus dem Manifest gesteuert)
            return \in_array($path, $coreConfigs, true);
        }

        // 3. Whitelist erlaubt
        foreach ($whitelist as $allowed) {
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
    private function isProtectedPath(string $path, array $blacklist): bool
    {
        // Wir nutzen die Blacklist aus dem Manifest als Schutz-Schild!
        // Was nicht überschrieben werden darf, darf auch nicht gelöscht werden.
        foreach ($blacklist as $protectedPrefix) {
            if (\str_starts_with($path, $protectedPrefix)) {
                return true;
            }
        }

        // Spezifischer Schutz für alle Formate im logo Ordner
        if (\str_starts_with($path, 'public/assets/img/logo/')) {
            return true;
        }

        return false;
    }

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
            @$todo($fileinfo->getRealPath());
        }

        @\rmdir($dir);
    }

    // --- Low-Level Network Core (Private) ---

    /**
     * Macht die rohen GitHub-API-Abrufe
     *
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
