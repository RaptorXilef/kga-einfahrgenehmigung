<?php

declare(strict_types=1);

namespace App\Modules\System\Application\Services;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\System\Domain\AuditLog;
use App\Modules\System\Domain\AuditLogRepositoryInterface;
use App\SharedKernel\Domain\ValueObject\IpAddress;

/**
 * Service for logging domain and system events securely.
 */
final readonly class AuditLoggerService
{
    public function __construct(
        private AuthSessionInterface $session,
        private ClockInterface $clock,
        private AuditLogRepositoryInterface $repository,
        private ConfigInterface $config,
    ) {
    }

    /**
     * Logs an action with the current user context and IP address.
     *
     * @param string $action A short identifier for the action (e.g., 'PERMIT_CREATE')
     * @param string $details A detailed description of the event
     */
    public function log(string $action, string $details): void
    {
        $userId = $this->session->getUserId();

        // BUGFIX: Stealth Mode (Unsichtbarkeit) über Config steuerbar machen!
        // Standardmäßig auf "false" setzen, damit Superadmins im Log auftauchen.
        $stealthMode = (bool) $this->config->get('stealth_superadmins', false);
        if ($stealthMode && \str_starts_with($userId, 'sys_')) {
            return;
        }

        // Identifiziere den Akteur: Wenn es kein Admin ist, ist es evtl. ein Pächter im History-Log!
        if ($userId === '') {
            $historyEmail = $this->session->getHistoryEmail();
            if ($historyEmail !== null && $historyEmail !== '') {
                $userId = 'history_user';
                $username = 'Pächter (' . $historyEmail . ')';
            } else {
                $userId = 'public_user';
                $username = 'Pächter / Öffentlicher Nutzer';
            }
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
