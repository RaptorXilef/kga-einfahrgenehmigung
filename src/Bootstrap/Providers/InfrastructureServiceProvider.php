<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Bootstrap\Container;
use App\Contracts\Bootstrap\ServiceProviderInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Contracts\Storage\MagicLinkRepositoryInterface;
use App\Contracts\Storage\MailQueueRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Service\AuthService;
use App\Infrastructure\Database\PdoFactory;
use App\Infrastructure\Mail\MailQueueService;
use App\Infrastructure\Mail\SmtpMailService;
use App\Infrastructure\Maintenance\BackupService;
use App\Infrastructure\Maintenance\MigrationService;
use App\Infrastructure\Maintenance\StorageBootstrapper;
use App\Infrastructure\Payment\PayPalService;
use App\Infrastructure\Security\RateLimiter;
use App\Infrastructure\Storage\FileLockManager;
use App\Infrastructure\Storage\GroupRepository;
use App\Infrastructure\Storage\MagicLinkRepository;
use App\Infrastructure\Storage\MailQueueRepository;
use App\Infrastructure\Storage\PermitArchiveRepository;
use App\Infrastructure\Storage\StorageFactory;
use App\Infrastructure\Storage\UserRepository;
use App\Infrastructure\Storage\VerificationRepository;
use App\Infrastructure\Storage\VoucherRepository;

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

        // --- 1.2 Repositories (Datenzugriff) ---
        $container->bind(GroupRepositoryInterface::class, fn () => new GroupRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));
        $container->bind(UserRepositoryInterface::class, fn () => new UserRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));
        $container->bind(PermitArchiveRepositoryInterface::class, fn () => new PermitArchiveRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));
        $container->bind(VerificationRepositoryInterface::class, fn () => new VerificationRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));
        $container->bind(VoucherRepositoryInterface::class, fn () => new VoucherRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));
        $container->bind(MagicLinkRepositoryInterface::class, fn () => new MagicLinkRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));
        $container->bind(MailQueueRepositoryInterface::class, fn () => new MailQueueRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));
        // --- 1.3 Netzwerk & Drittanbieter (Mail, PayPal) ---
        $container->bind('mail.smtp', fn (): SmtpMailService => new SmtpMailService($container->get(\PDO::class), $container->get(ConfigInterface::class)));
        $container->bind(MailLogInterface::class, fn () => $container->get('mail.smtp'));

        $container->bind(MailServiceInterface::class, fn (): MailQueueService => new MailQueueService(
            $container->get(MailQueueRepositoryInterface::class),
            $container->get('mail.smtp'),
        ));

        $container->bind(PaymentProviderInterface::class, fn (): PayPalService => new PayPalService(
            $container->get(ConfigInterface::class),
        ));
        // --- 1.4 Sicherheit & System-Bootstrapping ---
        $container->bind(RateLimiterInterface::class, fn () => new RateLimiter(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));
        $container->bind(LockManagerInterface::class, fn () => new FileLockManager(
            $container->get(ConfigInterface::class),
        ));

        $container->bind(StorageBootstrapper::class, fn (): StorageBootstrapper => new StorageBootstrapper(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(UserRepositoryInterface::class),
        ));

        // --- 1.5 System-Maintenance & Wartung ---
        $container->bind(BackupService::class, fn (): BackupService => new BackupService(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(MigrationService::class, fn (): MigrationService => new MigrationService(
            $container->get(\PDO::class),
            $container->get(AuthService::class),
            $container->get(BackupService::class),
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(MagicLinkRepositoryInterface::class),
            $container->get(MailLogInterface::class),
            $container->get(PermitArchiveRepositoryInterface::class),
            $container->get(StorageInterface::class),
            $container->get(UserRepositoryInterface::class),
            $container->get(VerificationRepositoryInterface::class),
            $container->get(VoucherRepositoryInterface::class),
        ));

        $container->bind(BackupServiceInterface::class, fn () => $container->get(
            BackupService::class,
        ));
    }
}
