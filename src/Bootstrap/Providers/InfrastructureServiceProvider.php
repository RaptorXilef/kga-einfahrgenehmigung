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
use App\Contracts\System\AssetHelperInterface;
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
use App\Infrastructure\Storage\JsonHelper;
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
use App\Infrastructure\System\LocalAssetHelper;
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
         */
        $container->bind(AuditLogRepositoryInterface::class, fn () => new MySqlAuditLogRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(CancelledPermitRepositoryInterface::class, fn () => new MySqlCancelledPermitRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

        $container->bind(RoleRepositoryInterface::class, fn () => new MySqlRoleRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

        $container->bind(LoginAttemptRepositoryInterface::class, fn () => new MySqlLoginAttemptRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(MagicLinkRepositoryInterface::class, fn () => new MySqlMagicLinkRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(MailQueueRepositoryInterface::class, fn () => new MySqlMailQueueRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

        $container->bind(PermitArchiveRepositoryInterface::class, fn () => new MySqlPermitArchiveRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

        $container->bind(UserRepositoryInterface::class, fn () => new MySqlUserRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(VerificationRepositoryInterface::class, fn () => new MySqlVerificationRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

        $container->bind(VoucherRepositoryInterface::class, fn () => new MySqlVoucherRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

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

        $container->bind(AssetHelperInterface::class, function () use ($container): LocalAssetHelper {
            $config = $container->get(ConfigInterface::class);
            \assert($config instanceof ConfigInterface);

            return new LocalAssetHelper($config);
        });
    }
}
