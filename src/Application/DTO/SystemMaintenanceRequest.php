<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Application\Exception\ValidationException;

/**
 * DTO für System-Wartungsaufgaben.
 * Nutzt "Named Constructors", um je nach Aufgabe streng zu validieren.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SystemMaintenanceRequest
{
    private function __construct(
        public string $target,
        public string $direction,
        public string $timestamp,
        public string $engine,
    ) {
    }

    // TODO DOCBLOCK
    public static function forMigration(array $post): self
    {
        $target = \trim((string) ($post['target'] ?? ''));
        if ($target === '') {
            throw ValidationException::withMessage('Fehler: Kein Zielbereich ausgewählt.');
        }

        return new self($target, \trim((string) ($post['direction'] ?? 'sync')), '', 'all');
    }

    public static function forRestore(array $post): self
    {
        $target    = \trim((string) ($post['target'] ?? ''));
        $timestamp = \trim((string) ($post['timestamp'] ?? ''));

        if ($target === '' || $timestamp === '') {
            throw ValidationException::withMessage('Fehler: Unvollständige Angaben für Restore.');
        }

        return new self($target, '', $timestamp, \trim((string) ($post['engine'] ?? 'all')));
    }

    public static function forTruncate(array $post): self
    {
        $target = \trim((string) ($post['target'] ?? ''));
        if ($target === '') {
            throw ValidationException::withMessage('Fehler: Kein Zielbereich ausgewählt.');
        }

        return new self($target, '', '', \trim((string) ($post['engine'] ?? 'all')));
    }
}
