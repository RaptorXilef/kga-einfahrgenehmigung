<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Service zum Einlesen und Vergleichen von benutzerfreundlichen Release-Notes (Markdown).
 */
final readonly class ReleaseNotesService
{
    public function __construct(
        private ConfigInterface $config,
    ) {
    }

    /**
     * Liest ALLE Markdown-Dateien aus `/release_notes` aus (für die Historie).
     *
     * @return array<int, array{version: string, content: string, clean_version: string}>
     */
    public function getAllNotes(): array
    {
        return $this->parseNotesFromDir(null);
    }

    /**
     * Liest nur jene Dateien aus, die neuer sind als die vom Nutzer zuletzt bestätigte Version.
     *
     * @return array<int, array{version: string, content: string, clean_version: string}>
     */
    public function getUnreadNotes(string $lastSeenVersion): array
    {
        return $this->parseNotesFromDir($lastSeenVersion);
    }

    /**
     * Private Kern-Logik für das Einlesen und Vergleichen.
     */
    private function parseNotesFromDir(?string $lastSeenVersion): array
    {
        $dir = \rtrim((string) $this->config->get('root_path'), '/\\') . '/release_notes';
        if (!\is_dir($dir)) {
            return [];
        }

        // FIX: Linux ist Case-Sensitive. Wir prüfen auf .md und .MD Dateien!
        $filesMd = (array) \glob($dir . '/*.md');
        $filesMD = (array) \glob($dir . '/*.MD');
        $files = \array_values(\array_unique(\array_filter(\array_merge($filesMd, $filesMD))));

        if ($files === []) {
            return [];
        }

        $notes = [];
        $cleanUserVer = $lastSeenVersion !== null ? \ltrim($lastSeenVersion, 'vV') : null;

        foreach ($files as $file) {
            $ver = \basename($file, '.md');
            $ver = \basename($ver, '.MD'); // Doppelt hält besser
            $cleanFileVer = \ltrim($ver, 'vV');

            // Wenn kein Limit gesetzt ist (alle laden) ODER die Datei neuer ist
            if ($cleanUserVer === null || \version_compare($cleanFileVer, $cleanUserVer, '>')) {
                $notes[] = [
                    'version' => $ver,
                    'content' => \file_get_contents($file) ?: '',
                    'clean_version' => $cleanFileVer,
                ];
            }
        }

        // Absteigend sortieren (Neueste Version oben)
        \usort($notes, fn (array $a, array $b): int => \version_compare($b['clean_version'], $a['clean_version']));

        return $notes;
    }
}
