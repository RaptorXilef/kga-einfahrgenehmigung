<?php

declare(strict_types=1);

namespace App\Application\Session;

use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Utils\ClockInterface;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
use Override;

/**
 * Kapselt alle Zugriffe auf den globalen $_SESSION State.
 * Verhindert direkte Array-Mutationen in den Actions (Leaky Abstractions).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class SessionManager implements AuthSessionInterface
{
    private const int MAX_LIFETIME = 43200; // 12 Stunden absolutes Maximum
    // private const int IDLE_TIMEOUT = 7200;  // 2 Stunden Inaktivität führt zum Logout
    // ÄNDERUNG: Reduziert auf 30 Min. (Bietet 10 Min Puffer für den 20-Minuten JS-Timer)
    private const int IDLE_TIMEOUT = 1800;  // 30 Minuten Inaktivität

    public function __construct(
        private ClockInterface $clock = new SystemClock(),
    ) {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }
        $this->enforceServerSideTimeout();
    }

    /**
     * Setzt strikte serverseitige Timeouts durch, da moderne Browser
     * "lifetime=0" Session-Cookies oft absichtlich wiederherstellen.
     */
    private function enforceServerSideTimeout(): void
    {
        $now = $this->clock->now()->getTimestamp();

        if (!isset($_SESSION['session_created'])) {
            $_SESSION['session_created'] = $now;
            $_SESSION['last_activity'] = $now;

            return;
        }

        $userId = $_SESSION['user_id'] ?? '';
        $adminUser = $_SESSION['admin_user'] ?? '';
        $isAuthenticated = (\is_string($userId) && $userId !== '') || (\is_string($adminUser) && $adminUser !== '');

        // Idle Timeout: User war zu lange inaktiv
        if ($now - (int) ($_SESSION['last_activity'] ?? $now) > self::IDLE_TIMEOUT) {
            if ($isAuthenticated) {
                $this->destroy();
                \session_start();
            }
            $_SESSION['session_created'] = $now;
            $_SESSION['last_activity'] = $now;

            return;
        }

        // Absolute Timeout: Session existiert insgesamt zu lange
        if ($now - (int) $_SESSION['session_created'] > self::MAX_LIFETIME) {
            $this->destroy();
            \session_start();
            $_SESSION['session_created'] = $now;
            $_SESSION['last_activity'] = $now;

            return;
        }

        $_SESSION['last_activity'] = $now;
    }

    public function setFormData(array $data): void
    {
        $_SESSION['form_data'] = $data;
    }

    public function getFormData(): array
    {
        return \is_array($_SESSION['form_data'] ?? null) ? $_SESSION['form_data'] : [];
    }

    public function clearFormData(): void
    {
        unset($_SESSION['form_data']);
    }

    public function setEditState(string $email, string $token): void
    {
        $_SESSION['verified_email'] = $email;
        $_SESSION['edit_token'] = $token;
    }

    public function getVerifiedEmail(): ?string
    {
        return \is_string($_SESSION['verified_email'] ?? null) ? $_SESSION['verified_email'] : null;
    }

    public function getEditToken(): ?string
    {
        return \is_string($_SESSION['edit_token'] ?? null) ? $_SESSION['edit_token'] : null;
    }

    public function clearEditState(): void
    {
        unset($_SESSION['verified_email'], $_SESSION['edit_token']);
    }

    public function setAdminFilters(array $filters): void
    {
        $_SESSION['admin_filters'] = $filters;
    }

    public function getAdminFilters(): array
    {
        return \is_array($_SESSION['admin_filters'] ?? null) ? $_SESSION['admin_filters'] : [];
    }

    public function clearAdminFilters(): void
    {
        unset($_SESSION['admin_filters']);
    }

    #[Override]
    public function setHistoryEmail(string $email): void
    {
        $_SESSION['user_history_email'] = $email;
    }

    #[Override]
    public function getHistoryEmail(): ?string
    {
        return \is_string($_SESSION['user_history_email'] ?? null) ? $_SESSION['user_history_email'] : null;
    }

    #[Override]
    public function clearHistoryEmail(): void
    {
        unset($_SESSION['user_history_email']);
    }

    public function updateAdminUsername(string $newName): void
    {
        $_SESSION['admin_user'] = $newName;
    }

    // --- AUFGABEN-SPEICHER FÜR SAMMELÜBERWEISUNGEN ---
    public function addCollectiveTransfer(array $transfer): void
    {
        $_SESSION['collective_transfers'][$transfer['id']] = $transfer;
    }

    public function getCollectiveTransfers(): array
    {
        return \is_array($_SESSION['collective_transfers'] ?? null) ? $_SESSION['collective_transfers'] : [];
    }

    public function removeCollectiveTransfer(string $id): void
    {
        unset($_SESSION['collective_transfers'][$id]);
    }

    // --- AUTH & SECURITY ---
    #[Override]
    public function regenerate(): void
    {
        \session_regenerate_id(true);
    }

    #[Override]
    public function destroy(): void
    {
        $_SESSION = [];
        if ((bool) \ini_get('session.use_cookies')) {
            $p = \session_get_cookie_params();
            $sessionName = \session_name();
            if (\is_string($sessionName) && $sessionName !== '') {
                \setcookie(
                    $sessionName,
                    '',
                    [
                        'expires' => $this->clock->now()->getTimestamp() - 42000,
                        'path' => $p['path'],
                        'domain' => $p['domain'],
                        'secure' => $p['secure'],
                        'httponly' => $p['httponly'],
                    ],
                );
            }
        }
        \session_destroy();
    }

    #[Override]
    public function setAuthSession(string $userId, string $groupId, string $label, ?string $hash = null): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['admin_user'] = $label;
        $_SESSION['admin_group'] = $groupId;
        if ($hash === null || $hash === '') {
            return;
        }

        $_SESSION['auth_hash'] = $hash;
    }

    #[Override]
    public function getAuthHash(): ?string
    {
        return \is_string($_SESSION['auth_hash'] ?? null) ? $_SESSION['auth_hash'] : null;
    }

    #[Override]
    public function setPermissions(array $perms): void
    {
        $_SESSION['compiled_permissions'] = $perms;
    }

    #[Override]
    public function getPermissions(): array
    {
        return \is_array($_SESSION['compiled_permissions'] ?? null) ? $_SESSION['compiled_permissions'] : [];
    }

    #[Override]
    public function getUserId(): string
    {
        return (string) ($_SESSION['user_id'] ?? '');
    }

    #[Override]
    public function getAdminGroup(): string
    {
        return (string) ($_SESSION['admin_group'] ?? 'guest');
    }

    #[Override]
    public function getAdminUser(): string
    {
        return (string) ($_SESSION['admin_user'] ?? 'Unbekannt');
    }

    // --- INFRASTRUCTURE ---
    public function setAnalyticsId(string $id): void
    {
        $_SESSION['ga4_client_id'] = $id;
    }

    public function getAnalyticsId(): ?string
    {
        return \is_string($_SESSION['ga4_client_id'] ?? null) ? $_SESSION['ga4_client_id'] : null;
    }

    public function getAnalyticsSessionId(): ?int
    {
        return \is_int($_SESSION['ga4_session_id'] ?? null) ? $_SESSION['ga4_session_id'] : null;
    }

    public function setAnalyticsSessionId(int $timestamp): void
    {
        $_SESSION['ga4_session_id'] = $timestamp;
    }

    public function initCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token']) || !\is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
            $_SESSION['csrf_token'] = \bin2hex(\random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public function getCsrfToken(): string
    {
        return \is_string($_SESSION['csrf_token'] ?? null) ? $_SESSION['csrf_token'] : '';
    }

    /**
     * Rotiert das CSRF-Token (wichtig bei Authentifizierungs-Wechseln).
     */
    #[Override]
    public function rotateCsrfToken(): void
    {
        $_SESSION['csrf_token'] = \bin2hex(\random_bytes(32));
    }

    public function setFormStartTime(int $timestamp): void
    {
        $_SESSION['form_start_time'] = $timestamp;
    }

    public function getFormStartTime(): int
    {
        return (int) ($_SESSION['form_start_time'] ?? 0);
    }

    public function clearFormStartTime(): void
    {
        unset($_SESSION['form_start_time']);
    }

    /**
     * Speichert eine Flash-Message in der Session.
     * $type ist z.B. 'success', 'error', 'warning', 'info'
     */
    public function addFlash(string $type, string $message): void
    {
        $_SESSION['flashes'][$type][] = $message;
    }

    /**
     * Liest alle Flash-Messages aus und löscht sie danach sofort.
     */
    public function getFlashes(): array
    {
        $flashes = \is_array($_SESSION['flashes'] ?? null) ? $_SESSION['flashes'] : [];
        unset($_SESSION['flashes']);

        return $flashes;
    }
}
