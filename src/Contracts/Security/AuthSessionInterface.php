<?php

declare(strict_types=1);

namespace App\Contracts\Security;

interface AuthSessionInterface
{
    public function setAuthSession(string $userId, string $groupId, string $label, ?string $hash = null): void;

    public function getAuthHash(): ?string;

    public function setPermissions(array $perms): void;

    public function getPermissions(): array;

    public function getUserId(): string;

    public function getAdminGroup(): string;

    public function getAdminUser(): string;

    // --- NEU: History Session Methods ---
    public function setHistoryEmail(string $email): void;

    public function getHistoryEmail(): ?string;

    public function clearHistoryEmail(): void;
    // ------------------------------------

    public function regenerate(): void;

    public function rotateCsrfToken(): void;

    public function destroy(): void;
}
