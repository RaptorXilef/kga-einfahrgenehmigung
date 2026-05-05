<?php

// SPDX-License-Identifier: LicenseRef-Proprietary
// Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
// Usage without explicit permission is strictly prohibited.
// See LICENSE.md for full license details.

/**
 * Interface für den E-Mail-Versanddienst.
 *
 * Path:      src/Contracts/Mail/MailServiceInterface.php
 *
 * @since     0.1.0
 * - feat(mail): Definition der Schnittstelle für Template-basierten Mailversand.
 */

declare(strict_types=1);

namespace App\Contracts\Mail;

interface MailServiceInterface
{
    /**
     * Sendet eine E-Mail basierend auf einem Template.
     *
     * @param string               $recipient Empfänger-Adresse.
     * @param string               $subject   Betreffzeile.
     * @param string               $template  Pfad zum Template relativ zum Template-Ordner.
     * @param array<string, mixed> $data      Daten für die Platzhalter.
     *
     * @return bool|string True bei Erfolg, Fehlermeldung als String bei Fehlern.
     */
    public function sendTemplate(string $recipient, string $subject, string $template, array $data): bool|string;
}
($users[$username]) && \password_verify($password, (string) $users[$username]['pass'])) {
            $this->setSession($username, (int) $users[$username]['level'], (string) ($users[$username]['label'] ?? ''));

            return true;
        }

        return false;
    }

    private function setSession(string $user, int $level, string $label): void
    {
        $_SESSION['admin_user']  = $user;
        $_SESSION['admin_level'] = $level;
        $_SESSION['admin_label'] = $label;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function loadUsers(): array
    {
        $path = $this->config->get('root_path') . '/storage/users.json';
        if (! \file_exists($path)) {
            return [];
        }

        return \json_decode((string) \file_get_contents($path), true) ?? [];
    }

    /**
     * @param array<string, array<string, mixed>> $users
     */
    public function saveUsers(array $users): void
    {
        $path = $this->config->get('root_path') . '/storage/users.json';
        \file_put_contents($path, \json_encode($users, \JSON_PRETTY_PRINT));
    }

    public function isLoggedIn(): bool
    {
        // NEU: Wenn Dev-Mode aktiv, immer "eingeloggt"
        if ($this->config->get('admin_dev_mode', false) === true) {
            return true;
        }

        return isset($_SESSION['admin_level']);
    }

    public function getLevel(): int
    {
        // Wenn Dev-Mode aktiv, immer Level 0 (Vollzugriff)
        if ($this->config->get('admin_dev_mode', false) === true) {
            return 0; // Im Dev-Mode immer Superadmin
        }

        return (int) ($_SESSION['admin_level'] ?? 3);
    }

    public function logout(): void
    {
        \session_destroy();
    }
}
