<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Config\ConfigInterface;

/**
 * Service zum Einlesen und Vergleichen von benutzerfreundlichen Release-Notes (Markdown).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class ReleaseNotesService
{
    public function __construct(private ConfigInterface $config)
    {
    }

    /**
     * Liest alle Markdown-Dateien aus `/release_notes` und gibt jene zurück,
     * die neuer sind als die vom Nutzer zuletzt bestätigte Version.
     *
     * @return array<int, array{version: string, content: string, clean_version: string}>
     */
    public function getUnreadNotes(string $lastSeenVersion): array
    {
        $dir = \rtrim((string) $this->config->get('root_path'), '/\\') . '/release_notes';
        if (!\is_dir($dir)) {
            return [];
        }

        $files = \glob($dir . '/*.md');
        if ($files === false || $files === []) {
            return [];
        }

        $notes = [];
        $cleanUserVer = \ltrim($lastSeenVersion, 'vV');

        foreach ($files as $file) {
            $ver = \basename($file, '.md');
            $cleanFileVer = \ltrim($ver, 'vV');

            // Wenn die Datei-Version neuer ist als die vom Nutzer bestätigte Version
            if (\version_compare($cleanFileVer, $cleanUserVer, '>')) {
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
