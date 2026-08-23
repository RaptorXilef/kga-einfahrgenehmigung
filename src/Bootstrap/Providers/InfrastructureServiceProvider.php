<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Application\Session\SessionManager;
use App\Contracts\Bootstrap\ServiceProviderInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\DependencyInjection\ContainerInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Maintenance\MigrationServiceInterface;
use App\Contracts\Maintenance\UpdateMigrationServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\AuditLogRepositoryInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Contracts\Storage\CronStateRepositoryInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Contracts\Storage\LoginAttemptRepositoryInterface;
use App\Contracts\Storage\MagicLinkRepositoryInterface;
use App\Contracts\Storage\MailQueueRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\RoleRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Contracts\System\AssetHelperInterface; // <-- NEU
use App\Contracts\System\ErrorLoggerInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\System\RouteCacheInterface;
use App\Contracts\System\StorageBootstrapperInterface;
use App\Contracts\System\SystemInfoInterface;
use App\Contracts\System\SystemUpdaterInterface;
use App\Contracts\Utils\ClockInterface;
use App\Infrastructure\Database\PdoFactory;
use App\Infrastructure\Logging\ErrorLogger;
use App\Infrastructure\Mail\MailQueueService;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Maintenance\BackupService;
use App\Infrastructure\Maintenance\GitHubUpdaterService;
use App\Infrastructure\Maintenance\MigrationService;
use App\Infrastructure\Maintenance\StorageBootstrapper;
use App\Infrastructure\Maintenance\UpdateMigrationService;
use App\Infrastructure\Payment\PayPalService;
use App\Infrastructure\Security\RateLimiter;
use App\Infrastructure\Storage\FileCronStateRepository;
use App\Infrastructure\Storage\FileLockManager;
use App\Infrastructure\Storage\ImageStorageService;
use App\Infrastructure\Storage\JsonAuditLogRepository;
use App\Infrastructure\Storage\JsonCancelledPermitRepository;
use App\Infrastructure\Storage\JsonHelper;
use App\Infrastructure\Storage\JsonLoginAttemptRepository;
use App\Infrastructure\Storage\JsonMagicLinkRepository;
use App\Infrastructure\Storage\JsonMailQueueRepository;
use App\Infrastructure\Storage\JsonPermitArchiveRepository;
use App\Infrastructure\Storage\JsonRoleRepository;
use App\Infrastructure\Storage\JsonUserRepository;
use App\Infrastructure\Storage\JsonVerificationRepository;
use App\Infrastructure\Storage\JsonVoucherRepository;
use App\Infrastructure\Storage\MySqlAuditLogRepository;
use App\Infrastructure\Storage\MySqlCancelledPermitRepository;
use App\Infrastructure\Storage\MySqlLoginAttemptRepository;
use App\Infrastructure\Storage\MySqlMagicLinkRepository;
use App\Infrastructure\Storage\MySqlMailQueueRepository;
use App\Infrastructure\Storage\MySqlPermitArchiveRepository;
use App\Infrastructure\Storage\MySqlRoleRepository;
use App\Infrastructure\Storage\MySqlUserRepository;
use App\Infrastructure\Storage\MySqlVerificationRepository;
use App\Infrastructure\Storage\MySqlVoucherRepository;
use App\Infrastructure\Storage\StorageFactory;
use App\Infrastructure\System\FileRouteCache;
use App\Infrastructure\System\LocalAssetHelper; // <-- NEU
use App\Infrastructure\System\SystemInfoService;
use App\Infrastructure\Utils\SystemClock;
use PDO;

/**
 * Der InfrastructureServiceProvider.
 *
 * Registriert alle Hardware-, Netzwerk- und Dateisystem-nahen Komponenten
 * im Dependency Injection Container der Anwendung. Diese Schicht stellt
 * sicher, dass die Core-Logik ausschließlich mit Interfaces (Contracts)
 * kommuniziert, ohne die tatsächlichen Implementierungsdetails (z.B.
 * MySQL, JSON, PayPal, SMTP) zu kennen.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class InfrastructureServiceProvider implements ServiceProviderInterface
{
    /**
     * Bindet alle Infrastruktur-Dienste an ihre entsprechenden Interfaces im DI-Container.
     *
     * @param ContainerInterface $container Der Dependency Injection Container der Applikation.
     */
    public function register(ContainerInterface $container): void
    {
        /*
         |--------------------------------------------------------------------------
         | 1. CORE SYSTEM & DATABASE
         |--------------------------------------------------------------------------
         | Grundlegende Datenbankverbindungen und persistente Systemspeicher.
         */
        $container->bind(PDO::class, fn (): ?PDO => PdoFactory::create(
            $container->get(ConfigInterface::class),
        ));

        $container->bind(StorageInterface::class, fn (): StorageInterface => StorageFactory::create(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

        // Mapping des System-Clocks für testbare Zeitstempel
        $container->bind(ClockInterface::class, fn (): mixed => $container->get(SystemClock::class));

        /*
         |--------------------------------------------------------------------------
         | 2. DATA REPOSITORIES (FACTORY PATTERN)
         |--------------------------------------------------------------------------
         | Entscheidet zur Laufzeit anhand der Konfiguration (storage_config),
         | ob die Daten im JSON-Flatfile oder in einer MySQL-Tabelle landen.
         */
        $container->bind(AuditLogRepositoryInterface::class, function () use ($container): MySqlAuditLogRepository|JsonAuditLogRepository {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['audit_logs']['type'] ?? 'json') === 'mysql'
                ? new MySqlAuditLogRepository($container->get(PDO::class), $config)
                : new JsonAuditLogRepository($config, $container->get(JsonHelperInterface::class));
        });

        $container->bind(CancelledPermitRepositoryInterface::class, function () use ($container): MySqlCancelledPermitRepository|JsonCancelledPermitRepository {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['permits_cancelled']['type'] ?? 'json') === 'mysql'
                ? new MySqlCancelledPermitRepository($container->get(PDO::class), $config, $container->get(JsonHelperInterface::class))
                : new JsonCancelledPermitRepository($config, $container->get(JsonHelperInterface::class));
        });

        $container->bind(RoleRepositoryInterface::class, function () use ($container): MySqlRoleRepository|JsonRoleRepository {
            $config = $container->get(ConfigInterface::class);
            \assert($config instanceof ConfigInterface);

            return ($config->get('storage_config')['roles']['type'] ?? 'json') === 'mysql'
                ? new MySqlRoleRepository($container->get(PDO::class), $config, $container->get(JsonHelperInterface::class))
                : new JsonRoleRepository($config, $container->get(JsonHelperInterface::class));
        });

        $container->bind(LoginAttemptRepositoryInterface::class, function () use ($container): MySqlLoginAttemptRepository|JsonLoginAttemptRepository {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['login_attempts']['type'] ?? 'json') === 'mysql'
                ? new MySqlLoginAttemptRepository($container->get(PDO::class), $config)
                : new JsonLoginAttemptRepository($config, $container->get(JsonHelperInterface::class));
        });

        $container->bind(MagicLinkRepositoryInterface::class, function () use ($container): MySqlMagicLinkRepository|JsonMagicLinkRepository {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['magic_links']['type'] ?? 'json') === 'mysql'
                ? new MySqlMagicLinkRepository($container->get(PDO::class), $config)
                : new JsonMagicLinkRepository($config, $container->get(JsonHelperInterface::class));
        });

        $container->bind(MailQueueRepositoryInterface::class, function () use ($container): MySqlMailQueueRepository|JsonMailQueueRepository {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['mail_queue']['type'] ?? 'json') === 'mysql'
                ? new MySqlMailQueueRepository($container->get(PDO::class), $config, $container->get(JsonHelperInterface::class))
                : new JsonMailQueueRepository($config, $container->get(JsonHelperInterface::class));
        });

        $container->bind(PermitArchiveRepositoryInterface::class, function () use ($container): MySqlPermitArchiveRepository|JsonPermitArchiveRepository {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['permits_archive']['type'] ?? 'json') === 'mysql'
                ? new MySqlPermitArchiveRepository($container->get(PDO::class), $config, $container->get(JsonHelperInterface::class))
                : new JsonPermitArchiveRepository($config, $container->get(JsonHelperInterface::class));
        });

        $container->bind(UserRepositoryInterface::class, function () use ($container): MySqlUserRepository|JsonUserRepository {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['users']['type'] ?? 'json') === 'mysql'
                ? new MySqlUserRepository($container->get(PDO::class), $config)
                : new JsonUserRepository($config, $container->get(JsonHelperInterface::class));
        });

        $container->bind(VerificationRepositoryInterface::class, function () use ($container): MySqlVerificationRepository|JsonVerificationRepository {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['pending_verification']['type'] ?? 'json') === 'mysql'
                ? new MySqlVerificationRepository($container->get(PDO::class), $config, $container->get(JsonHelperInterface::class))
                : new JsonVerificationRepository($config, $container->get(JsonHelperInterface::class));
        });

        $container->bind(VoucherRepositoryInterface::class, function () use ($container): MySqlVoucherRepository|JsonVoucherRepository {
            $config = $container->get(ConfigInterface::class);

            return ($config->get('storage_config')['vouchers']['type'] ?? 'json') === 'mysql'
                ? new MySqlVoucherRepository($container->get(PDO::class), $config, $container->get(JsonHelperInterface::class))
                : new JsonVoucherRepository($config, $container->get(JsonHelperInterface::class));
        });

        /*
         |--------------------------------------------------------------------------
         | 3. NETWORK & THIRD-PARTY SERVICES
         |--------------------------------------------------------------------------
         | Externe APIs, Payment-Provider und E-Mail Versand.
         */
        $container->bind(PaymentProviderInterface::class, fn (): mixed => $container->get(PayPalService::class));

        $container->bind('mail.smtp', fn (): SmtpMailService => new SmtpMailService(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

        $container->bind(MailLogInterface::class, fn (): mixed => $container->get('mail.smtp'));

        $container->bind(MailServiceInterface::class, fn (): MailQueueService => new MailQueueService(
            $container->get(MailQueueRepositoryInterface::class),
            $container->get('mail.smtp'),
        ));

        /*
         |--------------------------------------------------------------------------
         | 4. SECURITY & SESSION MANAGEMENT
         |--------------------------------------------------------------------------
         | Schutzmechanismen gegen Brute-Force, Dateizugriff und Auth-Handling.
         */
        $container->bind(AuthSessionInterface::class, fn (): object => clone $container->get(SessionManager::class));
        $container->bind(LockManagerInterface::class, fn (): mixed => $container->get(FileLockManager::class));
        $container->bind(RateLimiterInterface::class, fn (): mixed => $container->get(RateLimiter::class));

        /*
         |--------------------------------------------------------------------------
         | 5. SYSTEM, MAINTENANCE & UTILS
         |--------------------------------------------------------------------------
         | Hardware- und System-Tools für Backups, Updates, Migrationen und I/O.
         */
        $container->bind(BackupServiceInterface::class, fn (): mixed => $container->get(BackupService::class));
        $container->bind(CronStateRepositoryInterface::class, fn (): mixed => $container->get(FileCronStateRepository::class));
        $container->bind(ErrorLoggerInterface::class, fn (): mixed => $container->get(ErrorLogger::class));
        $container->bind(ImageStorageInterface::class, fn (): mixed => $container->get(ImageStorageService::class));
        $container->bind(JsonHelperInterface::class, fn (): JsonHelper => new JsonHelper());
        $container->bind(StorageBootstrapperInterface::class, fn (): mixed => $container->get(StorageBootstrapper::class));
        $container->bind(SystemInfoInterface::class, fn (): mixed => $container->get(SystemInfoService::class));
        $container->bind(SystemUpdaterInterface::class, fn (): mixed => $container->get(GitHubUpdaterService::class));
        $container->bind(UpdateMigrationServiceInterface::class, fn (): mixed => $container->get(UpdateMigrationService::class));

        // Haupt-Migrations-Dienst
        $container->bind(MigrationServiceInterface::class, fn (): MigrationService => new MigrationService(
            $container->get(PDO::class),
            $container->get(BackupServiceInterface::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

        // Route Cache Binding für die ActionRegistry
        $container->bind(RouteCacheInterface::class, function () use ($container): FileRouteCache {
            $config = $container->get(ConfigInterface::class);
            \assert($config instanceof ConfigInterface);

            return new FileRouteCache($config);
        });

        // <-- NEU: Hier ist das fehlende Binding
        $container->bind(AssetHelperInterface::class, function () use ($container): LocalAssetHelper {
            $config = $container->get(ConfigInterface::class);
            \assert($config instanceof ConfigInterface);

            return new LocalAssetHelper($config);
        });
    }
}
