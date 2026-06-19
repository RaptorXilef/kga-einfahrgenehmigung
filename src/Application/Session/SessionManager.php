<?php

declare(strict_types=1);

namespace App\Application\Session;

/**
 * Kapselt alle Zugriffe auf den globalen $_SESSION State.
 * Verhindert direkte Array-Mutationen in den Actions (Leaky Abstractions).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class SessionManager
{
    public function setFormData(array $data): void
    {
        $_SESSION['form_data'] = $data;
    }

    public function getFormData(): array
    {
        return $_SESSION['form_data'] ?? [];
    }

    public function clearFormData(): void
    {
        unset($_SESSION['form_data']);
    }

    public function setEditState(string $email, string $token): void
    {
        $_SESSION['verified_email'] = $email;
        $_SESSION['edit_token']     = $token;
    }

    public function getVerifiedEmail(): ?string
    {
        return $_SESSION['verified_email'] ?? null;
    }

    public function getEditToken(): ?string
    {
        return $_SESSION['edit_token'] ?? null;
    }

    public function clearEditState(): void
    {
        unset($_SESSION['verified_email'], $_SESSION['edit_token']);
    }

    public function setAdminFilters(array $filters): void
    {
        $_SESSION['admin_filters'] = $filters;
    }

    public function getAdminFilters(): array
    {
        return $_SESSION['admin_filters'] ?? [];
    }

    public function clearAdminFilters(): void
    {
        unset($_SESSION['admin_filters']);
    }

    public function setHistoryEmail(string $email): void
    {
        $_SESSION['user_history_email'] = $email;
    }

    public function getHistoryEmail(): ?string
    {
        return $_SESSION['user_history_email'] ?? null;
    }

    public function clearHistoryEmail(): void
    {
        unset($_SESSION['user_history_email']);
    }
}
