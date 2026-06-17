<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\VerificationActionFactory;
use App\Application\Security\CsrfHelper;

/**
 * Front Controller zur Verifizierung von E-Mail-Adressen (Double-Opt-In).
 *
 * Schützt die Route vor CSRF-Angriffen und delegiert die Logik
 * an spezialisierte Action-Klassen über die VerificationActionFactory.
 *
 * Path: src/Application/VerificationController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class VerificationController
{
    public function __construct(
        private VerificationActionFactory $factory,
    ) {
    }

    /**
     * Haupt-Request-Handler für den Double-Opt-In-Prozess.
     *
     * @param array<string, mixed> $get  Entspricht $_GET
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleRequest(array $get, array $post): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (! CsrfHelper::verify($post)) {
                \header('Location: verify.php?error=1&msg=' . \urlencode('Sicherheits-Token ungültig (CSRF). Bitte Seite neu laden.'));
                exit;
            }
        }

        $action = $this->factory->create($get, $post);

        $action->execute([
            'get'  => $get,
            'post' => $post,
            'ip'   => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
    }
}
