<?php

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Infrastructure\Auth\AuthService;

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

        // NEU: Zugriff für Superadmin (0) UND Admin (1)
        if (! $this->auth->isLoggedIn() || $myLevel > 1) {
            \header('Location: admin.php');

            return;
        }

        $message = '';
        if (isset($post['action'])) {
            $message = $this->handleAdminActions($post, $myLevel);
        }

        $this->render('admin_users', [
            'users'      => $this->auth->loadUsers(),
            'roles'      => $this->config->get('roles', []),
            'myLevel'    => $myLevel,
            'myUsername' => (string) ($_SESSION['admin_user'] ?? ''),
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
        $targetLevel = isset($users[$targetUser]) ? (int) $users[$targetUser]['level'] : 99;

        // --- SICHERHEITS-CHECK ---
        // Superadmin (0) darf alles.
        // Admin (1) darf nur sich selbst (PW/Label) oder User mit Level > 1.
        $isSelf    = $targetUser === ($_SESSION['admin_user'] ?? '');
        $canManage = ($myLevel === 0) || ($myLevel === 1 && ($targetLevel > 1 || $isSelf));

        if (! $canManage) {
            return 'Fehler: Unzureichende Berechtigung für diesen Benutzer.';
        }

        if ($action === 'save') {
            $password = (string) ($post['password'] ?? '');
            $newLevel = (int) ($post['level'] ?? $targetLevel);

            // Sicherheits-Sperre: Admin (1) kann niemanden zum Superadmin (0) machen
            if ($myLevel === 1 && $newLevel === 0) {
                $newLevel = $targetLevel;
            }

            // User-Daten bauen
            $userData = [
                'level' => $newLevel,
                'label' => \trim((string) ($post['label'] ?? '')),
            ];

            // Passwort nur überschreiben, wenn eines eingegeben wurde
            if ($password !== '') {
                $userData['pass'] = \password_hash($password, \PASSWORD_DEFAULT);
            } else {
                $userData['pass'] = $users[$targetUser]['pass'];
            }

            $users[$targetUser] = $userData;
            $this->auth->saveUsers($users);

            return "Benutzer '{$targetUser}' aktualisiert.";
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
        $appRoot = (string) $this->config->get('root_path');
        \extract($data);
        include $appRoot . "/templates/pages/{$templatePath}.phtml";
    }
}
