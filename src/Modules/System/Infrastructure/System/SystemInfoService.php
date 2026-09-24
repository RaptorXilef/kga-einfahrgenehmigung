<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\System;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\System\SystemInfoInterface;
use Exception;

final readonly class SystemInfoService implements SystemInfoInterface
{
    public function __construct(
        private ConfigInterface $config,
        private JsonHelperInterface $jsonHelper,
    ) {
    }

    public function getChangelog(): string
    {
        $path = \rtrim((string) $this->config->get('root_path'), '/\\') . '/CHANGELOG.md';
        if (!\file_exists($path)) {
            $path = \str_replace('.md', '.MD', $path);
        }

        return \file_exists($path) ? \file_get_contents($path) : 'Kein Changelog gefunden.';
    }

    public function getCurrentVersion(): string
    {
        $path = \rtrim((string) $this->config->get('root_path'), '/\\') . '/package.json';
        if (\file_exists($path)) {
            try {
                $data = $this->jsonHelper->read($path);
                if (isset($data['version'])) {
                    return 'v' . $data['version'];
                }
            } catch (Exception) {
            }
        }

        return 'v0.0.0';
    }

    public function getAllReleaseNotes(): array
    {
        return $this->parseNotesFromDir(null);
    }

    public function getUnreadReleaseNotes(string $lastSeenVersion): array
    {
        return $this->parseNotesFromDir($lastSeenVersion);
    }

    private function parseNotesFromDir(?string $lastSeenVersion): array
    {
        $dir = \rtrim((string) $this->config->get('root_path'), '/\\') . '/release_notes';
        if (!\is_dir($dir)) {
            return [];
        }

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
            $ver = \basename($ver, '.MD');
            $cleanFileVer = \ltrim($ver, 'vV');

            if ($cleanUserVer !== null && !\version_compare($cleanFileVer, $cleanUserVer, '>')) {
                continue;
            }

            $notes[] = [
                'version' => $ver,
                'content' => \file_get_contents($file) ?: '',
                'clean_version' => $cleanFileVer,
            ];
        }

        \usort($notes, fn (array $a, array $b): int => \version_compare($b['clean_version'], $a['clean_version']));

        return $notes;
    }
}
