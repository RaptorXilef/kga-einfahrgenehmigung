<?php

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Infrastructure\Auth\AuthService;
use App\Infrastructure\Config\Config;

/**
 * Orchestriert die Benutzerverwaltung für v0.9.7.
 */
final readonly class UserController
{
    public function __construct(
        private ConfigInterface $config,
        private AuthService $auth,
    ) {
    }

    /**
     * @param array<string, mixed> $post
     */
    public function handleRequest(array $post): void
    {
        $myLevel = $this->auth->getLevel();

        // Zugriff für Superadmin (0) UND Admin (1)
        if (! $this->auth->isLoggedIn() || $myLevel > 1) {
            \header('Location: admin.php');

            return;
        }

        $message = isset($post['action'])
            ? $this->handleAdminActions($post, $myLevel)
            : '';

        /** @var array<int, string> $roles */
        $roles = $this->config->get('roles', []);

        $this->render('admin_users', [
            'users'      => $this->auth->loadUsers(),
            'roles'      => $roles,
            'myLevel'    => $myLevel,
            'myUsername' => (string) ($_SESSION['admin_user'] ?? 'Unbekannt'),
            'message'    => $message,
            'settings'   => $this->getSettingsArray(),
            'config'     => $this->config,
            'appRoot'    => $this->config->get('root_path'),
        ]);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function handleAdminActions(array $post, int $myLevel): string
    {
        $users       = $this->auth->loadUsers();
        $action      = (string) ($post['action'] ?? '');
        $targetUser  = \trim((string) ($post['username'] ?? ''));
        $targetLevel = isset($users[$targetUser]) ? (int) $users[$targetUser]['level'] : 99; // Level des Ziel-Users
        $isSelf      = $targetUser === ($_SESSION['admin_user'] ?? '');

        // 1. Berechtigungs-Check (Guard Clause)
        // Superadmin darf alles.
        // Admin darf nur sich selbst oder User mit Level > 1 (also 2 und 3).
        if (! $this->canManage($myLevel, $targetLevel, $isSelf)) {
            return 'Fehler: Keine Berechtigung für diesen Benutzer.';
        }

        // 2. Action-Routing
        if ($action === 'save') {
            return $this->performSave($users, $targetUser, $post, $myLevel, $isSelf);
        }

        if ($action === 'delete' && ! $isSelf) {
            unset($users[$targetUser]);
            $this->auth->saveUsers($users);

            return "Benutzer '{$targetUser}' gelöscht.";
        }

        return '';
    }

    private function canManage(int $myLevel, int $targetLevel, bool $isSelf): bool
    {
        if ($myLevel === 0) {
            return true;
        }

        // Admin (1) darf nur >= 2 verwalten oder sich selbst
        return $myLevel === 1 && ($targetLevel > 1 || $isSelf);
    }

    /**
     * @param array<string, array<string, mixed>> $users
     * @param array<string, mixed>                $post
     */
    private function performSave(array $users, string $targetUser, array $post, int $myLevel, bool $isSelf): string
    {
        $targetData   = $users[$targetUser] ?? null;
        $currentLevel = isset($targetData) ? (int) $targetData['level'] : 99;

        // Level-Logik extrahieren
        $newLevel = $this->determineNewLevel((int) ($post['level'] ?? $currentLevel), $myLevel, $isSelf);

        // Passwort-Logik extrahieren
        $passHash = $this->determinePasswordHash((string) ($post['password'] ?? ''), $targetData);

        if ($passHash === null) {
            return 'Fehler: Passwort erforderlich.';
        }

        $users[$targetUser] = [
            'level' => $newLevel,
            'label' => \trim((string) ($post['label'] ?? '')),
            'pass'  => $passHash,
        ];

        $this->auth->saveUsers($users);

        return "Benutzer '{$targetUser}' gespeichert.";
    }

    private function determineNewLevel(int $requestedLevel, int $myLevel, bool $isSelf): int
    {
        // Superadmin (0) darf jedes Level setzen
        if ($myLevel === 0) {
            return $requestedLevel;
        }

        // Admin (1) darf bei anderen nur Level 2 oder 3 vergeben
        if (! $isSelf && $requestedLevel <= 1) {
            return 3;
        }

        return $requestedLevel;
    }

    /**
     * @param array<string, mixed>|null $targetData
     */
    private function determinePasswordHash(string $newPassword, ?array $targetData): ?string
    {
        if ($newPassword !== '') {
            return \password_hash($newPassword, \PASSWORD_DEFAULT);
        }

        if ($targetData !== null) {
            return (string) $targetData['pass'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name' => $this->config->get('vereins_name'),
            'jahresFarbe'  => $this->config->get('jahresFarbe'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $templatePath, array $data = []): void
    {
        /** @var Config $config */
        $config  = $this->config;
        $appRoot = (string) $config->get('root_path');
        \extract($data);
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
