<?php

declare(strict_types=1);

namespace App\Contracts\Security;

/**
 * Port für die Autorisierung und Abfrage des aktiven Admin-Kontexts.
 * Entkoppelt alle Module vom konkreten AuthService des Identity-Moduls.
 */
interface AuthorizationInterface
{
    public function hasPermission(string $permission): bool;

    public function isLoggedIn(): bool;

    public function getUsername(): string;

    public function getUserId(): string;

    public function getRole(): string;

    public function getLastSeenChangelog(): string;

    public function bootstrapDefaultIdentityData(): void;

    public function refreshSessionPermissions(string $roleId): void;

    public function logout(): void;
}
