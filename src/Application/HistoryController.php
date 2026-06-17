<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\Actions\HistoryActionFactory;
use App\Application\Security\CsrfHelper;
use App\Contracts\Security\RateLimiterInterface;

/**
 * Front Controller für die historische Antragsübersicht von Endnutzern.
 *
 * Sichert die Route durch Rate-Limiting und CSRF-Prüfungen ab und delegiert
 * die Logik an spezialisierte Action-Klassen über die HistoryActionFactory.
 *
 * Path: src/Application/HistoryController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class HistoryController
{
    public function __construct(
        private HistoryActionFactory $actionFactory,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    /**
     * Haupt-Request-Handler für die Benutzerhistorie.
     *
     * @param array<string, mixed> $get  Entspricht $_GET
     * @param array<string, mixed> $post Entspricht $_POST
     */
    public function handleRequest(array $get, array $post): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // 0. Zentrale Sicherheitsprüfung: IP-Sperre
        if ($this->rateLimiter->isBlocked($ip)) {
            $msg = 'Zu viele Versuche. Die IP-Adresse wurde für 15 Minuten gesperrt.';
            \header('Location: history.php?sent=0&msg=' . \urlencode($msg));
            exit;
        }

        // 1. Globale CSRF-Prüfung für alle POST-Requests
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (! CsrfHelper::verify($post)) {
                $msg = 'Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
                \header('Location: history.php?sent=0&msg=' . \urlencode($msg));
                exit;
            }
        }

        // 2. Aktion über die Factory auflösen und mit gebündelten Daten ausführen
        $action = $this->actionFactory->create($get, $post);

        $action->execute([
            'get'  => $get,
            'post' => $post,
            'ip'   => $ip,
        ]);
    }
}
