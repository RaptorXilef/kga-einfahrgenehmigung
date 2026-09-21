<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\UseCases\AuthenticateAdmin;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface;
use App\SharedKernel\Application\Command\CommandHandlerInterface;
use DomainException;

/**
 * @implements CommandHandlerInterface<AuthenticateAdminCommand>
 */
final readonly class AuthenticateAdminHandler implements CommandHandlerInterface
{
    public function __construct(
        private UserRepositoryInterface $repository,
        private AuthSessionInterface $session,
        private RateLimiterInterface $rateLimiter,
        private ConfigInterface $config,
    ) {
    }

    /**
     * @param AuthenticateAdminCommand $command
     */
    public function handle(mixed $command): void
    {
        // 1. Brute-Force Schutz
        if ($this->rateLimiter->isBlocked($command->ipAddress)) {
            throw new DomainException('Zu viele Fehlversuche. IP ist vorübergehend gesperrt.');
        }

        // 2. Fallback: Superadmins / Backdoor aus der Config (dev_admin.php)
        $superadmins = $this->config->get('superadmins', []);
        $backdoor = $this->config->get('backdoor', []);

        // Backdoor prüfen
        if (
            $command->username === ($backdoor['user'] ?? '')
            && \password_verify($command->password, $backdoor['pass'] ?? '')
            && !$this->config->get('disable_backdoor', false)
        ) {
            $this->loginSuccess('sys_backdoor', 'admin', $backdoor['label'] ?? 'System', null, $command->ipAddress);

            return;
        }

        // Konfigurierte Superadmins prüfen
        if (isset($superadmins[$command->username]) && !$this->config->get('disable_superadmin', false)) {
            $admin = $superadmins[$command->username];
            // Superadmins in KGA Legacy nutzen Klartext in der PHP-Config
            if ($command->password === ($admin['pass'] ?? '')) {
                $this->loginSuccess('sys_' . $command->username, 'admin', $admin['label'] ?? 'Systembetreuer', null, $command->ipAddress);

                return;
            }
        }

        // 3. Echte Datenbank-User prüfen
        $user = $this->repository->findByUsername($command->username);

        if ($user !== null && $user->verifyPassword($command->password)) {
            $this->loginSuccess($user->id, $user->roleId, $user->username, $user->getPasswordHash(), $command->ipAddress);

            return;
        }

        // 4. Bei Fehler: Strike registrieren und abbrechen
        $this->rateLimiter->recordFailedAttempt($command->ipAddress);

        throw new DomainException('Benutzername oder Passwort ist falsch.');
    }

    private function loginSuccess(string $userId, string $roleId, string $label, ?string $hash, string $ip): void
    {
        $this->session->regenerate();
        $this->session->rotateCsrfToken();
        $this->session->setAuthSession($userId, $roleId, $label, $hash);
        $this->rateLimiter->clearAttempts($ip);
    }
}
