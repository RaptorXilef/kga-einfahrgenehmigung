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
        // Sicherheits-Check: Nur eingeloggt und nur Level 0 (Superadmin)
        if (! $this->auth->isLoggedIn() || $this->auth->getLevel() !== 0) {
            \header('Location: admin.php');

            return;
        }

        $message = '';
        if (isset($post['action'])) {
            $message = $this->handleAdminActions($post);
        }

        // Wir holen die Rollen aus der Config (Stelle sicher, dass diese in der config.php stehen!)
        /** @var array<int, string> $roles */
        $roles = $this->config->get('roles', []);

        $this->render('admin_users', [
            'users'            => $this->auth->loadUsers(),
            'roles'            => $roles,
            'currentUserLevel' => $this->auth->getLevel(),
            'message'          => $message,
            'settings'         => $this->getSettingsArray(),
            'config'           => $this->config, // FIX: Wichtig für den Test-Mode-Indikator!
            'appRoot'          => $this->config->get('root_path'), // FIX: Wichtig für Includes im Template!
        ]);
    }

    /**
     * @param array<string, mixed> $post
     */
    private function handleAdminActions(array $post): string
    {
        $users    = $this->auth->loadUsers();
        $action   = (string) ($post['action'] ?? '');
        $username = \trim((string) ($post['username'] ?? ''));

        if ($action === 'create' || $action === 'update') {
            $users[$username] = [
                'pass'  => \password_hash((string) ($post['password'] ?? ''), \PASSWORD_DEFAULT),
                'level' => (int) ($post['level'] ?? 3),
                'label' => \trim((string) ($post['label'] ?? '')),
            ];
            $this->auth->saveUsers($users);

            return "Benutzer '{$username}' wurde gespeichert.";
        }

        if ($action === 'delete') {
            unset($users[$username]);
            $this->auth->saveUsers($users);

            return "Benutzer '{$username}' gelöscht.";
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
