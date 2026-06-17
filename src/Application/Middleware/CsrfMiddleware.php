<?php

declare(strict_types=1);

namespace App\Application\Middleware;

use App\Application\Security\CsrfHelper;
use App\Contracts\Application\MiddlewareInterface;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Middleware/CsrfMiddleware.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private string $fallbackUrl)
    {
    }

    // TODO DOCBLOCK
    public function process(array $requestData, callable $next): mixed
    {
        // CSRF greift nur bei POST-Requests!
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $post = $requestData['post'] ?? [];

            if (! CsrfHelper::verify($post)) {
                $msg = 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
                $url = $this->fallbackUrl . (\str_contains($this->fallbackUrl, '?') ? '&' : '?') . 'msg=' . \urlencode($msg);

                \header("Location: $url");
                exit;
            }
        }

        // Alles okay! Weiter zur nächsten Schicht.
        return $next($requestData);
    }
}
