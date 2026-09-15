<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * Generisches DTO für Aktionen, die nur eine einzige ID oder einen Code benötigen.
 */
final readonly class SimpleIdentifierRequest
{
    private function __construct(
        public string $identifier,
    ) {
    }

    public static function fromArray(array $post, string $keyName): self
    {
        $identifier = \trim((string) ($post[$keyName] ?? ''));

        // FIX: Strikte Regex-Prüfung gegen Path-Traversal (erlaubt nur alphanumerisch, Bindestriche, Unterstriche)
        // Ausnahme: Wenn es ein Datum ist (für Mail-Logs), sind auch Leerzeichen und Doppelpunkte erlaubt
        $pattern = \str_contains($keyName, 'timestamp') ? '/^[a-zA-Z0-9_\-\s:]+$/' : '/^[a-zA-Z0-9_\-]+$/';

        if ($identifier === '' || !\preg_match($pattern, $identifier)) {
            throw ValidationException::withMessage("Fehler: Ungültiger oder fehlender Parameter ($keyName).");
        }

        return new self($identifier);
    }
}
