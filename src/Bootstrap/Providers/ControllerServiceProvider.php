<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Application\AdminController;
use App\Application\CheckController;
use App\Application\CheckoutController;
use App\Application\HistoryController;
use App\Application\LegalController;
use App\Application\PaymentController;
use App\Application\PermitController;
use App\Application\SuccessController;
use App\Application\UserController;
use App\Application\VerificationController;
use App\Application\View\TemplateRenderer;
use App\Bootstrap\Container;
use App\Contracts\Bootstrap\ServiceProviderInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Service\AuthService;
use App\Core\Service\BankQrGenerator;
use App\Core\Service\ExportService;
use App\Core\Service\HolidayService;
use App\Core\Service\MagicLinkService;
use App\Core\Service\Maintenance\CronScheduler;
use App\Core\Service\PermitService;
use App\Core\Service\ReportingService;
use App\Core\Service\VoucherService;

/**
 * Registriert sämtliche Controller und View-Renderer der Application.
 *
 * Path: src/Bootstrap/Providers/ControllerServiceProvider.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final class ControllerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // --- 3.1 View Renderer ---
        $container->bind(TemplateRenderer::class, fn (): TemplateRenderer => new TemplateRenderer(
            $container->get(ConfigInterface::class),
        ));

        // --- 3.2 Backend Controller (Admin) ---
        $container->bind(AdminController::class, fn (): AdminController => new AdminController(
            $container->get(AuthService::class),
            $container->get(\App\Infrastructure\Maintenance\BackupService::class),
            $container->get(ConfigInterface::class),
            $container->get(CronScheduler::class),
            $container->get(ExportService::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(HolidayService::class),
            $container->get(MailLogInterface::class),
            $container->get(MailServiceInterface::class),
            $container->get(\App\Infrastructure\Maintenance\MigrationService::class),
            $container->get(PermitArchiveRepositoryInterface::class),
            $container->get(PermitService::class),
            $container->get(ReportingService::class),
            $container->get(\App\Infrastructure\Maintenance\StorageBootstrapper::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
            $container->get(VoucherRepositoryInterface::class),
            $container->get(VoucherService::class),
        ));

        $container->bind(UserController::class, fn (): UserController => new UserController(
            $container->get(AuthService::class),
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
        ));

        // --- 3.3 Frontend Controller (Public) ---
        $container->bind(PermitController::class, fn (): PermitController => new PermitController(
            $container->get(ConfigInterface::class),
            $container->get(PermitService::class),
            $container->get(TemplateRenderer::class),
            $container->get(VerificationRepositoryInterface::class),
            $container->get(VoucherRepositoryInterface::class),
            $container->get(VoucherService::class),
        ));

        $container->bind(VerificationController::class, fn (): VerificationController => new VerificationController(
            $container->get(ConfigInterface::class),
            $container->get(MailServiceInterface::class),
            $container->get(PermitService::class),
            $container->get(RateLimiterInterface::class),
            $container->get(TemplateRenderer::class),
        ));

        $container->bind(CheckoutController::class, fn (): CheckoutController => new CheckoutController(
            $container->get(ConfigInterface::class),
            $container->get(HolidayService::class),
            $container->get(PermitService::class),
            $container->get(TemplateRenderer::class),
        ));

        $container->bind(PaymentController::class, fn (): PaymentController => new PaymentController(
            $container->get(PermitService::class),
        ));

        $container->bind(SuccessController::class, fn (): SuccessController => new SuccessController(
            $container->get(BankQrGenerator::class),
            $container->get(ConfigInterface::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
        ));

        $container->bind(CheckController::class, fn (): CheckController => new CheckController(
            $container->get(AuthService::class),
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(HolidayService::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
        ));

        $container->bind(HistoryController::class, fn (): HistoryController => new HistoryController(
            $container->get(ConfigInterface::class),
            $container->get(HolidayService::class),
            $container->get(MagicLinkService::class),
            $container->get(MailServiceInterface::class),
            $container->get(PermitService::class),
            $container->get(RateLimiterInterface::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
        ));

        $container->bind(LegalController::class, fn (): LegalController => new LegalController(
            $container->get(ConfigInterface::class),
            $container->get(TemplateRenderer::class),
        ));
    }
}
