<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Bootstrap\Container;
use App\Contracts\Bootstrap\ServiceProviderInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\LockManagerInterface;
use App\Contracts\Storage\MagicLinkRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Service\AuthService;
use App\Core\Service\BankQrGenerator;
use App\Core\Service\ExportService;
use App\Core\Service\HolidayService;
use App\Core\Service\LicensePlateFormatter;
use App\Core\Service\MagicLinkService;
use App\Core\Service\Maintenance\CronScheduler;
use App\Core\Service\PermitService;
use App\Core\Service\ReportingService;
use App\Core\Service\VoucherService;
use App\Infrastructure\Maintenance\GitHubUpdaterService;
use App\Infrastructure\Maintenance\UpdateMigrationService;

/**
 * Registriert die reine Geschäftslogik (Domain Services).
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class CoreServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // --- 2.1 Identität & Autorisierung ---
        $container->bind(AuthService::class, fn (): AuthService => new AuthService(
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(RateLimiterInterface::class),
            $container->get(UserRepositoryInterface::class),
        ));

        // --- 2.2 Haupt-Geschäftslogik ---
        $container->bind(PermitService::class, fn () => new PermitService(
            $container->get(ConfigInterface::class),
            $container->get(EventDispatcherInterface::class),
            $container->get(LicensePlateFormatter::class),
            $container->get(LockManagerInterface::class),
            $container->get(PermitArchiveRepositoryInterface::class),
            $container->get(StorageInterface::class),
            $container->get(VerificationRepositoryInterface::class),
            $container->get(VoucherService::class),
        ));

        $container->bind(VoucherService::class, fn (): VoucherService => new VoucherService(
            $container->get(VoucherRepositoryInterface::class),
        ));

        $container->bind(HolidayService::class, fn (): HolidayService => new HolidayService(
            $container->get(ConfigInterface::class),
        ));

        $container->bind(MagicLinkService::class, fn (): MagicLinkService => new MagicLinkService(
            $container->get(MagicLinkRepositoryInterface::class),
            $container->get(ConfigInterface::class),
        ));

        // --- 2.3 Werkzeuge & Formatierer ---
        $container->bind(LicensePlateFormatter::class, fn () => new LicensePlateFormatter());

        $container->bind(BankQrGenerator::class, fn () => new BankQrGenerator(
            $container->get(ConfigInterface::class),
        ));

        $container->bind(ReportingService::class, fn (): ReportingService => new ReportingService(
            $container->get(ConfigInterface::class),
        ));

        $container->bind(ExportService::class, fn (): ExportService => new ExportService(
            $container->get(ConfigInterface::class),
        ));

        // --- 2.4 Wartung & System-Updates ---
        $container->bind(UpdateMigrationService::class, fn (): UpdateMigrationService => new UpdateMigrationService(
            $container->get(ConfigInterface::class),
            $container->get(\PDO::class),
        ));

        $container->bind(GitHubUpdaterService::class, fn (): GitHubUpdaterService => new GitHubUpdaterService(
            $container->get(ConfigInterface::class),
        ));

        $container->bind(CronScheduler::class, fn () => new CronScheduler(
            $container->get(BackupServiceInterface::class),
            $container->get(ConfigInterface::class),
            $container->get(PermitService::class),
        ));
    }
}
