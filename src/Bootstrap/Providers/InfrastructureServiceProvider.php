<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Application\Session\SessionManager;
use App\Contracts\Bootstrap\ServiceProviderInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\DependencyInjection\ContainerInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Maintenance\UpdateMigrationServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\CancelledPermitRepositoryInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Contracts\Storage\MailQueueRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\System\ErrorLoggerInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\System\PdfGeneratorInterface;
use App\Contracts\System\RouteCacheInterface;
use App\Contracts\System\StorageBootstrapperInterface;
use App\Contracts\System\SystemInfoInterface;
use App\Contracts\Utils\ClockInterface;
use App\Infrastructure\Database\PdoFactory;
use App\Infrastructure\Logging\ErrorLogger;
use App\Infrastructure\Mail\MailQueueService;
use App\Infrastructure\Mail\MicrosoftGraphMailService;
use App\Infrastructure\Mail\OAuthSmtpMailService;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Maintenance\BackupService;
use App\Infrastructure\Maintenance\StorageBootstrapper;
use App\Infrastructure\Maintenance\UpdateMigrationService;
use App\Infrastructure\Payment\PayPalService;
use App\Infrastructure\Security\RateLimiter;
use App\Infrastructure\Storage\FileLockManager;
use App\Infrastructure\Storage\ImageStorageService;
use App\Infrastructure\Storage\JsonHelper;
use App\Infrastructure\Storage\MySqlCancelledPermitRepository;
use App\Infrastructure\Storage\MySqlMailQueueRepository;
use App\Infrastructure\Storage\MySqlPermitArchiveRepository;
use App\Infrastructure\Storage\MySqlVerificationRepository;
use App\Infrastructure\Storage\StorageFactory;
use App\Infrastructure\System\DompdfGenerator;
use App\Infrastructure\System\FileRouteCache;
use App\Infrastructure\System\LocalAssetHelper;
use App\Infrastructure\System\SystemInfoService;
use App\Infrastructure\Utils\SystemClock;
use App\Modules\Identity\Domain\LoginAttemptRepositoryInterface;
use App\Modules\Identity\Domain\MagicLinkRepositoryInterface as IdentityMagicLinkRepositoryInterface;
use App\Modules\Identity\Domain\RoleRepositoryInterface as IdentityRoleRepositoryInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface as IdentityUserRepositoryInterface;
use App\Modules\Identity\Infrastructure\PdoLoginAttemptRepository;
use App\Modules\Identity\Infrastructure\PdoMagicLinkRepository;
use App\Modules\Identity\Infrastructure\PdoRoleRepository;
use App\Modules\Identity\Infrastructure\PdoUserRepository as IdentityPdoUserRepository;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Infrastructure\PdoPermitRepository;
use App\Modules\System\Domain\AuditLogRepositoryInterface;
use App\Modules\System\Infrastructure\PdoAuditLogRepository;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface as NewVoucherRepositoryInterface;
use App\Modules\Voucher\Infrastructure\PdoVoucherRepository;
use PDO;

/**
 * Der InfrastructureServiceProvider.
 *
 * Registriert alle Hardware-, Netzwerk- und Dateisystem-nahen Komponenten
 * im Dependency Injection Container der Anwendung. Diese Schicht stellt
 * sicher, dass die Core-Logik ausschließlich mit Interfaces (Contracts)
 * kommuniziert, ohne die tatsächlichen Implementierungsdetails (z.B.
 * MySQL, JSON, PayPal, SMTP) zu kennen.
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
        // --- CORE SYSTEM & DATABASE ---
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

        // --- LEGACY REPOSITORIES ---
        $container->bind(CancelledPermitRepositoryInterface::class, fn (): MySqlCancelledPermitRepository => new MySqlCancelledPermitRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));
        $container->bind(MailQueueRepositoryInterface::class, fn (): MySqlMailQueueRepository => new MySqlMailQueueRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));
        $container->bind(PermitArchiveRepositoryInterface::class, fn (): MySqlPermitArchiveRepository => new MySqlPermitArchiveRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));
        $container->bind(VerificationRepositoryInterface::class, fn (): MySqlVerificationRepository => new MySqlVerificationRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

        // --- SYSTEM DDD REPOSITORY BINDINGS ---
        $container->bind(AuditLogRepositoryInterface::class, fn (): PdoAuditLogRepository => new PdoAuditLogRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
        ));

        // --- PERMIT DDD REPOSITORY BINDINGS ---
        $container->bind(PermitRepositoryInterface::class, fn (): PdoPermitRepository => new PdoPermitRepository(
            $container->get(PDO::class),
        ));

        // --- VOUCHER DDD REPOSITORY BINDINGS ---
        $container->bind(NewVoucherRepositoryInterface::class, fn (): PdoVoucherRepository => new PdoVoucherRepository(
            $container->get(PDO::class),
        ));

        // --- IDENTITY DDD REPOSITORY BINDINGS ---
        $container->bind(IdentityUserRepositoryInterface::class, fn (): IdentityPdoUserRepository => new IdentityPdoUserRepository(
            $container->get(PDO::class),
        ));
        $container->bind(IdentityMagicLinkRepositoryInterface::class, fn (): PdoMagicLinkRepository => new PdoMagicLinkRepository(
            $container->get(PDO::class),
        ));
        $container->bind(IdentityRoleRepositoryInterface::class, fn (): PdoRoleRepository => new PdoRoleRepository(
            $container->get(PDO::class),
        ));
        $container->bind(LoginAttemptRepositoryInterface::class, fn (): PdoLoginAttemptRepository => new PdoLoginAttemptRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
        ));

        // --- NETWORK & THIRD-PARTY SERVICES ---
        $container->bind(PaymentProviderInterface::class, fn (): mixed => $container->get(PayPalService::class));

        // Dynamische Mail-Transport Auflösung (Strategy Pattern)
        $container->bind('mail.transport', function () use ($container) {
            $config = $container->get(ConfigInterface::class);
            $default = $config->get('mail', [])['default'] ?? 'smtp';

            if ($default === 'graph') {
                return new MicrosoftGraphMailService(
                    $container->get(PDO::class),
                    $config,
                    $container->get(JsonHelperInterface::class),
                );
            }
            if ($default === 'oauth') {
                return new OAuthSmtpMailService(
                    $container->get(PDO::class),
                    $config,
                    $container->get(JsonHelperInterface::class),
                );
            }

            // Standard Fallback: Das bisherige System
            return new SmtpMailService(
                $container->get(PDO::class),
                $config,
                $container->get(JsonHelperInterface::class),
            );
        });

        // Logger Interface an den aktiven Transport binden
        $container->bind(MailLogInterface::class, fn (): mixed => $container->get('mail.transport'));

        // MailService mit dem aktiven Transport instanziieren
        $container->bind(MailServiceInterface::class, fn (): MailQueueService => new MailQueueService(
            $container->get(MailQueueRepositoryInterface::class),
            $container->get('mail.transport'),
        ));

        // --- SECURITY ---
        $container->bind(AuthSessionInterface::class, fn (): object => clone $container->get(SessionManager::class));
        $container->bind(LockManagerInterface::class, fn (): mixed => $container->get(FileLockManager::class));
        $container->bind(RateLimiterInterface::class, fn (): mixed => $container->get(RateLimiter::class));

        // --- SYSTEM ---
        $container->bind(BackupServiceInterface::class, fn (): mixed => $container->get(BackupService::class));
        $container->bind(ErrorLoggerInterface::class, fn (): mixed => $container->get(ErrorLogger::class));
        $container->bind(ImageStorageInterface::class, fn (): mixed => $container->get(ImageStorageService::class));
        $container->bind(JsonHelperInterface::class, fn (): JsonHelper => new JsonHelper());
        $container->bind(StorageBootstrapperInterface::class, fn (): mixed => $container->get(StorageBootstrapper::class));
        $container->bind(SystemInfoInterface::class, fn (): mixed => $container->get(SystemInfoService::class));
        $container->bind(UpdateMigrationServiceInterface::class, fn (): mixed => $container->get(UpdateMigrationService::class));

        // PDF Generator binden
        $container->bind(PdfGeneratorInterface::class, fn (): DompdfGenerator => new DompdfGenerator());

        // Route Cache Binding für die ActionRegistry
        $container->bind(RouteCacheInterface::class, fn (): FileRouteCache => new FileRouteCache(
            $container->get(ConfigInterface::class),
        ));
        $container->bind(AssetHelperInterface::class, fn (): LocalAssetHelper => new LocalAssetHelper(
            $container->get(ConfigInterface::class),
        ));
    }
}
