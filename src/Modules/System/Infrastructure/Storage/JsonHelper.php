<?php

declare(strict_types=1);

namespace App\Modules\System\Infrastructure\Storage;

use App\Contracts\System\JsonHelperInterface;
use JsonException;
use RuntimeException;

final class JsonHelper implements JsonHelperInterface
{
    public function decode(string $json): array
    {
        if (\trim($json) === '') {
            return [];
        }

        $pattern = '/("([^"\\\\]*|\\\\.)*")|(\/\*[\s\S]*?\*\/|\/\/.*)/';
        $jsonWithoutComments = \preg_replace($pattern, '$1', $json);

        try {
            return \json_decode(
                (string) $jsonWithoutComments,
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            throw new RuntimeException('Kritischer Fehler: JSON-Datenstruktur ist korrupt (' .
                $e->getMessage() .
                '). System-Halt zum Schutz vor Datenverlust.', $e->getCode(), $e);
        }
    }

    public function read(string $path): array
    {
        if (!\file_exists($path) || \is_dir($path)) {
            return [];
        }

        $content = \file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Kritischer Fehler: Datei konnte nicht gelesen werden: {$path}");
        }

        return $this->decode($content);
    }
}
