<?php

declare(strict_types=1);

namespace App\Application\Security;

/**
 * Hilfsklasse zur Validierung von CSRF-Tokens in Web-Aufrufen.
 *
 * Path: src/Application/Security/CsrfHelper.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
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
