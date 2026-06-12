<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

// TODO DOCBLOCK
final class JsonHelper
{
    /**
     * Decodiert einen JSON-String sicher. Bricht bei Fehlern das System ab.
     */
    public static function decode(string $json): array
    {
        if (\trim($json) === '') {
            return [];
        }

        try {
            return \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                'Kritischer Fehler: JSON-Datenstruktur ist korrupt (' . $e->getMessage() . '). System-Halt zum Schutz vor Datenverlust.',
            );
        }
    }

    /**
     * Liest eine JSON-Datei sicher ein.
     */
    public static function read(string $path): array
    {
        if (! \file_exists($path) || \is_dir($path)) {
            return [];
        }

        $content = \file_get_contents($path);

        if ($content === false) {
            throw new \RuntimeException("Kritischer Fehler: Datei konnte nicht gelesen werden: {$path}");
        }

        return self::decode($content);
    }
}
