<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Application\Session\SessionManager;
use App\Contracts\Bootstrap\ServiceProviderInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\DependencyInjection\ContainerInterface;
use App\Contracts\Integration\FinanceIntegrationInterface;
use App\Contracts\Integration\PermitIntegrationInterface;
use App\Contracts\Integration\VoucherIntegrationInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Maintenance\UpdateMigrationServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Security\AuthorizationInterface;
use App\Contracts\Security\AuthSessionInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Contracts\System\AssetHelperInterface;
use App\Contracts\System\ErrorLoggerInterface;
use App\Contracts\System\ImageStorageInterface;
use App\Contracts\System\IpResolverInterface;
use App\Contracts\System\JsonHelperInterface;
use App\Contracts\System\PdfGeneratorInterface;
use App\Contracts\System\RouteCacheInterface;
use App\Contracts\System\StorageBootstrapperInterface;
use App\Contracts\System\SystemInfoInterface;
use App\Contracts\Utils\ClockInterface;
use App\Modules\Finance\Application\Contracts\BankImportInfrastructureInterface;
use App\Modules\Finance\Application\Contracts\UnpaidPermitProviderInterface;
use App\Modules\Finance\Application\Services\FinanceIntegrationService;
use App\Modules\Finance\Infrastructure\Payment\LocalBankImportInfrastructure;
use App\Modules\Finance\Infrastructure\Payment\PayPalService;
use App\Modules\Finance\Infrastructure\PdoUnpaidPermitProvider;
use App\Modules\Identity\Application\Services\AuthService;
use App\Modules\Identity\Domain\LoginAttemptRepositoryInterface;
use App\Modules\Identity\Domain\MagicLinkRepositoryInterface as IdentityMagicLinkRepositoryInterface;
use App\Modules\Identity\Domain\RoleRepositoryInterface as IdentityRoleRepositoryInterface;
use App\Modules\Identity\Domain\UserRepositoryInterface as IdentityUserRepositoryInterface;
use App\Modules\Identity\Infrastructure\PdoLoginAttemptRepository;
use App\Modules\Identity\Infrastructure\PdoMagicLinkRepository;
use App\Modules\Identity\Infrastructure\PdoRoleRepository;
use App\Modules\Identity\Infrastructure\PdoUserRepository as IdentityPdoUserRepository;
use App\Modules\Identity\Infrastructure\Security\RateLimiter;
use App\Modules\Permit\Application\Services\PermitIntegrationService;
use App\Modules\Permit\Domain\CancelledPermitRepositoryInterface;
use App\Modules\Permit\Domain\PermitArchiveRepositoryInterface;
use App\Modules\Permit\Domain\PermitRepositoryInterface;
use App\Modules\Permit\Domain\VerificationRepositoryInterface;
use App\Modules\Permit\Infrastructure\PdoCancelledPermitRepository;
use App\Modules\Permit\Infrastructure\PdoPermitArchiveRepository;
use App\Modules\Permit\Infrastructure\PdoPermitRepository;
use App\Modules\Permit\Infrastructure\PdoVerificationRepository;
use App\Modules\System\Domain\AuditLogRepositoryInterface;
use App\Modules\System\Domain\MailQueueRepositoryInterface;
use App\Modules\System\Infrastructure\Logging\ErrorLogger;
use App\Modules\System\Infrastructure\Mail\MailQueueService;
use App\Modules\System\Infrastructure\Mail\MicrosoftGraphMailService;
use App\Modules\System\Infrastructure\Mail\OAuthSmtpMailService;
use App\Modules\System\Infrastructure\Mail\SmtpMailService;
use App\Modules\System\Infrastructure\Maintenance\BackupService;
use App\Modules\System\Infrastructure\Maintenance\StorageBootstrapper;
use App\Modules\System\Infrastructure\Maintenance\UpdateMigrationService;
use App\Modules\System\Infrastructure\PdoAuditLogRepository;
use App\Modules\System\Infrastructure\PdoMailQueueRepository;
use App\Modules\System\Infrastructure\Storage\FileLockManager;
use App\Modules\System\Infrastructure\Storage\ImageStorageService;
use App\Modules\System\Infrastructure\Storage\JsonHelper;
use App\Modules\System\Infrastructure\System\DompdfGenerator;
use App\Modules\System\Infrastructure\System\FileRouteCache;
use App\Modules\System\Infrastructure\System\LocalAssetHelper;
use App\Modules\System\Infrastructure\System\ServerIpResolver;
use App\Modules\System\Infrastructure\System\SystemInfoService;
use App\Modules\Voucher\Application\Services\VoucherIntegrationService;
use App\Modules\Voucher\Domain\VoucherArchiveRepositoryInterface;
use App\Modules\Voucher\Domain\VoucherRepositoryInterface as NewVoucherRepositoryInterface;
use App\Modules\Voucher\Infrastructure\PdoVoucherArchiveRepository;
use App\Modules\Voucher\Infrastructure\PdoVoucherRepository;
use App\SharedKernel\Infrastructure\Database\PdoFactory;
use App\SharedKernel\Infrastructure\Utils\SystemClock;
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

        // Mapping des System-Clocks für testbare Zeitstempel
        $container->bind(ClockInterface::class, fn (): mixed => $container->get(SystemClock::class));

        // --- SYSTEM DDD REPOSITORY BINDINGS ---
        $container->bind(AuditLogRepositoryInterface::class, fn (): PdoAuditLogRepository => new PdoAuditLogRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
        ));
        $container->bind(MailQueueRepositoryInterface::class, fn (): PdoMailQueueRepository => new PdoMailQueueRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

        // --- PERMIT DDD REPOSITORY BINDINGS ---
        $container->bind(PermitRepositoryInterface::class, fn (): PdoPermitRepository => new PdoPermitRepository(
            $container->get(PDO::class),
        ));
        $container->bind(VerificationRepositoryInterface::class, fn (): PdoVerificationRepository => new PdoVerificationRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));
        $container->bind(CancelledPermitRepositoryInterface::class, fn (): PdoCancelledPermitRepository => new PdoCancelledPermitRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
        ));

        // VSA FIX: ClockInterface wird jetzt injiziert!
        $container->bind(PermitArchiveRepositoryInterface::class, fn (): PdoPermitArchiveRepository => new PdoPermitArchiveRepository(
            $container->get(PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(JsonHelperInterface::class),
            $container->get(ClockInterface::class),
        ));

        // --- VOUCHER DDD REPOSITORY BINDINGS ---
        $container->bind(NewVoucherRepositoryInterface::class, fn (): PdoVoucherRepository => new PdoVoucherRepository(
            $container->get(PDO::class),
        ));
        $container->bind(VoucherArchiveRepositoryInterface::class, fn (): PdoVoucherArchiveRepository => new PdoVoucherArchiveRepository(
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

        // --- FINANCE DDD REPOSITORY BINDINGS ---
        $container->bind(UnpaidPermitProviderInterface::class, fn (): PdoUnpaidPermitProvider => new PdoUnpaidPermitProvider(
            $container->get(PDO::class),
        ));
        $container->bind(BankImportInfrastructureInterface::class, fn (): LocalBankImportInfrastructure => new LocalBankImportInfrastructure(
            $container->get(ConfigInterface::class),
        ));

        // --- INTEGRATION SERVICES (CROSS-MODULE PORTS) ---
        $container->bind(FinanceIntegrationInterface::class, fn (): FinanceIntegrationService => $container->get(FinanceIntegrationService::class));
        $container->bind(PermitIntegrationInterface::class, fn (): PermitIntegrationService => $container->get(PermitIntegrationService::class));
        $container->bind(VoucherIntegrationInterface::class, fn (): VoucherIntegrationService => $container->get(VoucherIntegrationService::class));

        // --- NETWORK & THIRD-PARTY SERVICES ---
        $container->bind(PaymentProviderInterface::class, fn (): mixed => $container->get(PayPalService::class));

        $container->bind('mail.transport', function () use ($container): MicrosoftGraphMailService|OAuthSmtpMailService|SmtpMailService {
            $config = $container->get(ConfigInterface::class);
            $default = $config->get('mail', [])['default'] ?? 'smtp';

            if ($default === 'graph') {
                return new MicrosoftGraphMailService(
                    $container->get(PDO::class),
                    $config,
                    $container->get(JsonHelperInterface::class),
                    $container->get(ClockInterface::class),
                );
            }
            if ($default === 'oauth') {
                return new OAuthSmtpMailService(
                    $container->get(PDO::class),
                    $config,
                    $container->get(JsonHelperInterface::class),
                    $container->get(ClockInterface::class),
                );
            }

            // Standard Fallback
            return new SmtpMailService(
                $container->get(PDO::class),
                $config,
                $container->get(JsonHelperInterface::class),
                $container->get(ClockInterface::class),
            );
        });

        // Logger Interface an den aktiven Transport binden
        $container->bind(MailLogInterface::class, fn (): mixed => $container->get('mail.transport'));

        // MailService mit dem aktiven Transport instanziieren
        $container->bind(MailServiceInterface::class, fn (): MailQueueService => new MailQueueService(
            $container->get(MailQueueRepositoryInterface::class),
            $container->get('mail.transport'),
            $container->get(ClockInterface::class),
        ));

        // --- SECURITY ---
        $container->bind(AuthSessionInterface::class, fn (): object => clone $container->get(SessionManager::class));
        $container->bind(RateLimiterInterface::class, fn (): mixed => $container->get(RateLimiter::class));
        $container->bind(AuthorizationInterface::class, fn (): mixed => $container->get(AuthService::class));

        // --- SYSTEM ---
        $container->bind(IpResolverInterface::class, fn (): ServerIpResolver => new ServerIpResolver());
        $container->bind(LockManagerInterface::class, fn (): mixed => $container->get(FileLockManager::class));
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
