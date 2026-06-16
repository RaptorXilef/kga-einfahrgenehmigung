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
use App\Core\Service\PermitService;

/**
 * Registriert alle Hardware-, Netzwerk- und Dateisystem-nahen Komponenten.
 *
 * Path: src/Bootstrap/Providers/InfrastructureServiceProvider.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final class InfrastructureServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // --- 1.1 Datenbank & Basis-Storage ---
        $container->bind(\PDO::class, fn (): ?\PDO => \App\Infrastructure\Database\PdoFactory::create(
            $container->get(ConfigInterface::class),
        ));

        $container->bind(StorageInterface::class, fn (): StorageInterface => \App\Infrastructure\Storage\StorageFactory::create(
            $container->get(ConfigInterface::class),
            $container->get(\PDO::class),
        ));

        // --- 1.2 Repositories (Datenzugriff) ---
        $container->bind(GroupRepositoryInterface::class, fn () => new \App\Infrastructure\Storage\GroupRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(UserRepositoryInterface::class, fn () => new \App\Infrastructure\Storage\UserRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(PermitArchiveRepositoryInterface::class, fn () => new \App\Infrastructure\Storage\PermitArchiveRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(VerificationRepositoryInterface::class, fn () => new \App\Infrastructure\Storage\VerificationRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(VoucherRepositoryInterface::class, fn () => new \App\Infrastructure\Storage\VoucherRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(MagicLinkRepositoryInterface::class, fn () => new \App\Infrastructure\Storage\MagicLinkRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(MailQueueRepositoryInterface::class, fn () => new \App\Infrastructure\Storage\MailQueueRepository(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));

        // --- 1.3 Netzwerk & Drittanbieter (Mail, PayPal) ---
        $container->bind('mail.smtp', fn (): \App\Infrastructure\Mail\SmtpMailService => new \App\Infrastructure\Mail\SmtpMailService(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(MailLogInterface::class, fn () => $container->get('mail.smtp'));
        $container->bind(MailServiceInterface::class, fn (): \App\Core\Service\MailQueueService => new \App\Core\Service\MailQueueService(
            $container->get(MailQueueRepositoryInterface::class),
            $container->get('mail.smtp'),
        ));

        $container->bind(PaymentProviderInterface::class, fn (): \App\Infrastructure\Payment\PayPalService => new \App\Infrastructure\Payment\PayPalService(
            $container->get(ConfigInterface::class),
        ));

        // --- 1.4 Sicherheit & System-Bootstrapping ---
        $container->bind(RateLimiterInterface::class, fn () => new \App\Infrastructure\Security\RateLimiter(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(LockManagerInterface::class, fn () => new \App\Infrastructure\Storage\FileLockManager(
            $container->get(ConfigInterface::class),
        ));

        $container->bind(\App\Infrastructure\Maintenance\StorageBootstrapper::class, fn (): \App\Infrastructure\Maintenance\StorageBootstrapper => new \App\Infrastructure\Maintenance\StorageBootstrapper(
            $container->get(\PDO::class),
            $container->get(AuthService::class),
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(UserRepositoryInterface::class),
        ));

        // --- 1.5 System-Maintenance & Wartung ---
        $container->bind(\App\Infrastructure\Maintenance\BackupService::class, fn (): \App\Infrastructure\Maintenance\BackupService => new \App\Infrastructure\Maintenance\BackupService(
            $container->get(\PDO::class),
            $container->get(ConfigInterface::class),
        ));

        $container->bind(\App\Infrastructure\Maintenance\MigrationService::class, fn (): \App\Infrastructure\Maintenance\MigrationService => new \App\Infrastructure\Maintenance\MigrationService(
            $container->get(\PDO::class),
            $container->get(AuthService::class),
            $container->get(\App\Infrastructure\Maintenance\BackupService::class),
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(MagicLinkRepositoryInterface::class),
            $container->get(MailLogInterface::class),
            $container->get(MailServiceInterface::class),
            $container->get(PermitArchiveRepositoryInterface::class),
            $container->get(PermitService::class),
            $container->get(StorageInterface::class),
            $container->get(UserRepositoryInterface::class),
            $container->get(VerificationRepositoryInterface::class),
            $container->get(VoucherRepositoryInterface::class),
        ));
    }
}
