<?php

declare(strict_types=1);

namespace App\Application\Security;

/**
 * Hilfsklasse zur Validierung von CSRF-Tokens in Web-Aufrufen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class CsrfHelper
{
    /**
     * Prüft das übergebene Token gegen die Session.
     *
     * @param array<string, mixed> $post Das globale $_POST Array.
     *
     * @return bool True, wenn das Token gültig ist.
     */
    public static function verify(array $post): bool
    {
        return \hash_equals($_SESSION['csrf_token'] ?? '', $post['csrf_token'] ?? '');
    }
}
