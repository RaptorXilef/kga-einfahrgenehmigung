<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Application\Session\SessionManager;
use App\Bootstrap\Container;
use App\Contracts\Bootstrap\ServiceProviderInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\AuditLogRepositoryInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Contracts\Storage\CronStateRepositoryInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Contracts\Storage\LoginAttemptRepositoryInterface;
use App\Contracts\Storage\MagicLinkRepositoryInterface;
use App\Contracts\Storage\MailQueueRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Contracts\Utils\ClockInterface;
use App\Infrastructure\Database\PdoFactory;
use App\Infrastructure\Mail\MailQueueService;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Maintenance\BackupService;
use App\Infrastructure\Payment\PayPalService;
use App\Infrastructure\Security\RateLimiter;
use App\Infrastructure\Storage\FileCronStateRepository;
use App\Infrastructure\Storage\FileLockManager;
use App\Infrastructure\Storage\JsonAuditLogRepository;
use App\Infrastructure\Storage\JsonCancelledPermitRepository;
use App\Infrastructure\Storage\JsonGroupRepository;
use App\Infrastructure\Storage\JsonLoginAttemptRepository;
use App\Infrastructure\Storage\JsonMagicLinkRepository;
use App\Infrastructure\Storage\JsonMailQueueRepository;
use App\Infrastructure\Storage\JsonPermitArchiveRepository;
use App\Infrastructure\Storage\JsonUserRepository;
use App\Infrastructure\Storage\JsonVerificationRepository;
use App\Infrastructure\Storage\JsonVoucherRepository;
use App\Infrastructure\Storage\MySqlAuditLogRepository;
use App\Infrastructure\Storage\MySqlCancelledPermitRepository;
use App\Infrastructure\Storage\MySqlGroupRepository;
use App\Infrastructure\Storage\MySqlLoginAttemptRepository;
use App\Infrastructure\Storage\MySqlMagicLinkRepository;
use App\Infrastructure\Storage\MySqlMailQueueRepository;
use App\Infrastructure\Storage\MySqlPermitArchiveRepository;
use App\Infrastructure\Storage\MySqlUserRepository;
use App\Infrastructure\Storage\MySqlVerificationRepository;
use App\Infrastructure\Storage\MySqlVoucherRepository;
use App\Infrastructure\Storage\StorageFactory;
use App\Infrastructure\Utils\SystemClock;

/**
 * Registriert alle Hardware-, Netzwerk- und Dateisystem-nahen Komponenten.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class InfrastructureServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // --- 1.1 Datenbank & Basis-Storage ---
        $container->bind(\PDO::class, fn (): ?\PDO => PdoFactory::create(
            $container->get(ConfigInterface::class),
        ));

        $container->bind(StorageInterface::class, fn (): StorageInterface => StorageFactory::create(
            $container->get(ConfigInterface::class),
            $container->get(\PDO::class),
        ));

        // Wir mappen das ClockInterface auf den FQCN, den das Autowiring auflösen kann
        $container->bind(ClockInterface::class, fn () => $container->get(SystemClock::class));

        // --- 1.2 Repositories (Datenzugriff) ---
        $container->bind(GroupRepositoryInterface::class, function () use ($container) {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['groups']['type'] ?? 'json') === 'mysql'
                ? new MySqlGroupRepository($container->get(\PDO::class), $config)
                : new JsonGroupRepository($config);
        });

        $container->bind(UserRepositoryInterface::class, function () use ($container) {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['users']['type'] ?? 'json') === 'mysql'
                ? new MySqlUserRepository($container->get(\PDO::class), $config)
                : new JsonUserRepository($config);
        });

        $container->bind(PermitArchiveRepositoryInterface::class, function () use ($container) {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['permits_archive']['type'] ?? 'json') === 'mysql'
                ? new MySqlPermitArchiveRepository($container->get(\PDO::class), $config)
                : new JsonPermitArchiveRepository($config);
        });

        $container->bind(CancelledPermitRepositoryInterface::class, function () use ($container) {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['permits_cancelled']['type'] ?? 'json') === 'mysql'
                ? new MySqlCancelledPermitRepository($container->get(\PDO::class), $config)
                : new JsonCancelledPermitRepository($config);
        });

        $container->bind(VerificationRepositoryInterface::class, function () use ($container) {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['pending_verification']['type'] ?? 'json') === 'mysql'
                ? new MySqlVerificationRepository($container->get(\PDO::class), $config)
                : new JsonVerificationRepository($config);
        });

        $container->bind(VoucherRepositoryInterface::class, function () use ($container) {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['vouchers']['type'] ?? 'json') === 'mysql'
                ? new MySqlVoucherRepository($container->get(\PDO::class), $config)
                : new JsonVoucherRepository($config);
        });

        $container->bind(MagicLinkRepositoryInterface::class, function () use ($container) {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['magic_links']['type'] ?? 'json') === 'mysql'
                ? new MySqlMagicLinkRepository($container->get(\PDO::class), $config)
                : new JsonMagicLinkRepository($config);
        });

        $container->bind(MailQueueRepositoryInterface::class, function () use ($container) {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['mail_queue']['type'] ?? 'json') === 'mysql'
                ? new MySqlMailQueueRepository($container->get(\PDO::class), $config)
                : new JsonMailQueueRepository($config);
        });

        $container->bind(LoginAttemptRepositoryInterface::class, function () use ($container) {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['login_attempts']['type'] ?? 'json') === 'mysql'
                ? new MySqlLoginAttemptRepository($container->get(\PDO::class), $config)
                : new JsonLoginAttemptRepository($config);
        });

        $container->bind(AuthSessionInterface::class, fn () => clone $container->get(
            SessionManager::class,
        ));
        $container->bind(CronStateRepositoryInterface::class, fn () => $container->get(
            FileCronStateRepository::class,
        ));

        $container->bind(AuditLogRepositoryInterface::class, function () use ($container) {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['audit_logs']['type'] ?? 'json') === 'mysql'
                ? new MySqlAuditLogRepository($container->get(\PDO::class), $config)
                : new JsonAuditLogRepository($config);
        });

        // --- 1.3 Netzwerk & Drittanbieter (Mail, PayPal) ---
        // Mail Decorator Pattern bleibt bestehen, um Rekursion bei MailQueueService zu verhindern
        $container->bind('mail.smtp', fn () => new SmtpMailService(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));
        $container->bind(MailLogInterface::class, fn () => $container->get(
            'mail.smtp',
        ));

        $container->bind(MailServiceInterface::class, fn () => new MailQueueService(
            $container->get(MailQueueRepositoryInterface::class),
            $container->get('mail.smtp'),
        ));

        // Alle restlichen Services löst der Container via Autowiring vollautomatisch auf!
        $container->bind(PaymentProviderInterface::class, fn () => $container->get(
            PayPalService::class,
        ));

        // --- 1.4 Sicherheit & System-Bootstrapping ---
        $container->bind(RateLimiterInterface::class, fn () => $container->get(
            RateLimiter::class,
        ));
        $container->bind(LockManagerInterface::class, fn () => $container->get(
            FileLockManager::class,
        ));

        // --- 1.5 System-Maintenance & Wartung ---
        $container->bind(BackupServiceInterface::class, fn () => $container->get(
            BackupService::class,
        ));
    }
}
