<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

/**
 * Hilfsklasse für sichere und erweiterte JSON-Operationen.
 * Unterstützt nativ das JSONC-Format (JSON mit ein- und mehrzeiligen Kommentaren).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class JsonHelper
{
    /**
     * Dekodiert einen JSON- oder JSONC-String in ein assoziatives PHP-Array.
     * Schützt vor Code-Injections und filtert Kommentare vor dem Parsing heraus.
     *
     * @param  string $json Der rohe JSON-String.
     * @return array  Assoziatives Daten-Array.
     */
    public static function decode(string $json): array
    {
        if (\trim($json) === '') {
            return [];
        }

        /**
         * Enterprise Regex-Kommentar-Filter:
         * 1. `/\*.*?\* /`  ` filtert mehrzeilige Blockkommentare.
         * 2. `(?<!:)\/\/.*` filtert einzeilige Kommentare, ignoriert aber Protokolle wie https://
         */
        $jsonWithoutComments = \preg_replace(
            '#/\*.*?\*/|(?<!:)\/\/.*#s',
            '',
            $json,
        );

        try {
            return \json_decode(
                (string) $jsonWithoutComments,
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                'Kritischer Fehler: JSON-Datenstruktur ist korrupt (' .
                    $e->getMessage() .
                    '). System-Halt zum Schutz vor Datenverlust.',
            );
        }
    }

    /**
     * Liest eine JSON/JSONC-Datei vom Datenträger ein.
     *
     * @param  string $path Vollständiger Dateipfad.
     * @return array  Assoziatives Daten-Array.
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
