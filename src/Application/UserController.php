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

        $message = '';
        if (isset($post['action'])) {
            $message = $this->handleAdminActions($post, $myLevel);
        }

        /** @var array<int, string> $roles */
        $roles = $this->config->get('roles', []);

        $this->render('admin_users', [
            'users'      => $this->auth->loadUsers(),
            'roles'      => $roles,
            'myLevel'    => $myLevel, // FIX: Wurde im Template erwartet
            'myUsername' => (string) ($_SESSION['admin_user'] ?? 'Unbekannt'), // FIX: Wurde im Template erwartet
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
        $users      = $this->auth->loadUsers();
        $action     = (string) ($post['action'] ?? '');
        $targetUser = \trim((string) ($post['username'] ?? ''));

        // Level des Ziel-Users ermitteln (99 falls neu)
        $targetLevel = isset($users[$targetUser]) ? (int) $users[$targetUser]['level'] : 99;
        $isSelf      = $targetUser === ($_SESSION['admin_user'] ?? '');

        // --- BERECHTIGUNGSPRÜFUNG ---
        // Superadmin darf alles.
        // Admin darf nur sich selbst oder User mit Level > 1 (also 2 und 3).
        $canManage = ($myLevel === 0) || ($myLevel === 1 && ($targetLevel > 1 || $isSelf));

        if (! $canManage) {
            return 'Fehler: Unzureichende Berechtigung.';
        }

        if ($action === 'save') {
            $newLevel = (int) ($post['level'] ?? $targetLevel);

            // Sicherheits-Sperre: Admin (1) kann niemanden auf Level 0 oder 1 hieven
            if ($myLevel === 1 && $newLevel <= 1 && ! $isSelf) {
                $newLevel = $targetLevel === 99 ? 3 : $targetLevel;
            }

            $userData = [
                'level' => $newLevel,
                'label' => \trim((string) ($post['label'] ?? '')),
            ];

            // Passwort-Handling (Passwort nur überschreiben, wenn eines eingegeben wurde)
            $password = (string) ($post['password'] ?? '');
            if ($password !== '') {
                $userData['pass'] = \password_hash($password, \PASSWORD_DEFAULT);
            } elseif (isset($users[$targetUser])) {
                $userData['pass'] = $users[$targetUser]['pass'];
            } else {
                return 'Fehler: Passwort für neuen Benutzer erforderlich.';
            }

            $users[$targetUser] = $userData;
            $this->auth->saveUsers($users);

            return "Benutzer '{$targetUser}' gespeichert.";
        }

        if ($action === 'delete' && ! $isSelf) {
            unset($users[$targetUser]);
            $this->auth->saveUsers($users);

            return "Benutzer '{$targetUser}' gelöscht.";
        }

        return '';
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
