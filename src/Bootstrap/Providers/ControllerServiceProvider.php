<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Application\Actions\AdminActionFactory;
use App\Application\Actions\AnonymizeArchiveAction;
use App\Application\Actions\CapturePaymentAction;
use App\Application\Actions\CheckoutAction;
use App\Application\Actions\CheckPermitAction;
use App\Application\Actions\ClearCacheAction;
use App\Application\Actions\CreateManualAction;
use App\Application\Actions\CreateVoucherAction;
use App\Application\Actions\DatenschutzAction;
use App\Application\Actions\DeleteVoucherAction;
use App\Application\Actions\FilterDashboardAction;
use App\Application\Actions\HistoryActionFactory;
use App\Application\Actions\HistoryLogoutAction;
use App\Application\Actions\HistoryPrintAction;
use App\Application\Actions\HistoryRenderAction;
use App\Application\Actions\HistoryRequestLinkAction;
use App\Application\Actions\HistorySubmitCodeAction;
use App\Application\Actions\HistoryVerifyTokenAction;
use App\Application\Actions\ImpressumAction;
use App\Application\Actions\LoginAction;
use App\Application\Actions\LogoutAction;
use App\Application\Actions\MarkAsPaidAction;
use App\Application\Actions\MigrateDataAction;
use App\Application\Actions\ResendMailAction;
use App\Application\Actions\RestoreDataAction;
use App\Application\Actions\ToggleSuspensionAction;
use App\Application\Actions\ToggleVoucherAction;
use App\Application\Actions\TruncateTargetAction;
use App\Application\AdminController;
use App\Application\HistoryController;
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
use App\Infrastructure\Maintenance\BackupService;
use App\Infrastructure\Maintenance\MigrationService;
use App\Infrastructure\Maintenance\StorageBootstrapper;

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
        // Admin Actions
        $container->bind(AnonymizeArchiveAction::class, fn () => new AnonymizeArchiveAction(
            $container->get(AuthService::class),
            $container->get(PermitArchiveRepositoryInterface::class),
        ));

        $container->bind(ClearCacheAction::class, fn () => new ClearCacheAction(
            $container->get(AuthService::class),
            $container->get(MigrationService::class),
        ));

        $container->bind(CreateManualAction::class, fn () => new CreateManualAction(
            $container->get(AuthService::class),
            $container->get(PermitService::class),
        ));

        $container->bind(CreateVoucherAction::class, fn () => new CreateVoucherAction(
            $container->get(AuthService::class),
            $container->get(VoucherService::class),
        ));

        $container->bind(DeleteVoucherAction::class, fn () => new DeleteVoucherAction(
            $container->get(AuthService::class),
            $container->get(VoucherService::class),
        ));

        $container->bind(MarkAsPaidAction::class, fn () => new MarkAsPaidAction(
            $container->get(AuthService::class),
            $container->get(PermitService::class),
        ));

        $container->bind(FilterDashboardAction::class, fn () => new FilterDashboardAction());

        $container->bind(LoginAction::class, fn () => new LoginAction(
            $container->get(AuthService::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
        ));

        $container->bind(LogoutAction::class, fn () => new LogoutAction(
            $container->get(AuthService::class),
        ));

        $container->bind(MigrateDataAction::class, fn () => new MigrateDataAction(
            $container->get(AuthService::class),
            $container->get(MigrationService::class),
        ));

        $container->bind(ResendMailAction::class, fn () => new ResendMailAction(
            $container->get(AuthService::class),
            $container->get(MailLogInterface::class),
            $container->get(MailServiceInterface::class),
        ));

        $container->bind(RestoreDataAction::class, fn () => new RestoreDataAction(
            $container->get(AuthService::class),
            $container->get(MigrationService::class),
        ));

        $container->bind(ToggleSuspensionAction::class, fn () => new ToggleSuspensionAction(
            $container->get(AuthService::class),
            $container->get(PermitService::class),
            $container->get(StorageInterface::class),
        ));

        $container->bind(ToggleVoucherAction::class, fn () => new ToggleVoucherAction(
            $container->get(AuthService::class),
            $container->get(VoucherService::class),
        ));

        $container->bind(TruncateTargetAction::class, fn () => new TruncateTargetAction(
            $container->get(AuthService::class),
            $container->get(MigrationService::class),
        ));

        $container->bind(AdminActionFactory::class, fn () => new AdminActionFactory(
            $container,
        ));

        $container->bind(AdminController::class, fn (): AdminController => new AdminController(
            $container->get(AdminActionFactory::class),
            $container->get(AuthService::class),
            $container->get(BackupService::class),
            $container->get(ConfigInterface::class),
            $container->get(CronScheduler::class),
            $container->get(ExportService::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(HolidayService::class),
            $container->get(MailLogInterface::class),
            $container->get(MailServiceInterface::class),
            $container->get(MigrationService::class),
            $container->get(PermitArchiveRepositoryInterface::class),
            $container->get(PermitService::class),
            $container->get(ReportingService::class),
            $container->get(StorageBootstrapper::class),
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
        $container->bind(CheckPermitAction::class, fn () => new CheckPermitAction(
            $container->get(AuthService::class),
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(HolidayService::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
        ));

        $container->bind(CheckoutAction::class, fn () => new CheckoutAction(
            $container->get(ConfigInterface::class),
            $container->get(HolidayService::class),
            $container->get(PermitService::class),
            $container->get(TemplateRenderer::class),
        ));

        // History Actions
        $container->bind(HistoryLogoutAction::class, fn () => new HistoryLogoutAction());

        $container->bind(HistoryRequestLinkAction::class, fn () => new HistoryRequestLinkAction(
            $container->get(ConfigInterface::class),
            $container->get(MagicLinkService::class),
            $container->get(MailServiceInterface::class),
            $container->get(PermitService::class),
            $container->get(RateLimiterInterface::class),
        ));
        $container->bind(HistorySubmitCodeAction::class, fn () => new HistorySubmitCodeAction(
            $container->get(MagicLinkService::class),
            $container->get(RateLimiterInterface::class),
        ));
        $container->bind(HistoryVerifyTokenAction::class, fn () => new HistoryVerifyTokenAction(
            $container->get(MagicLinkService::class),
            $container->get(RateLimiterInterface::class),
        ));
        $container->bind(HistoryPrintAction::class, fn () => new HistoryPrintAction(
            $container->get(HolidayService::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
        ));
        $container->bind(HistoryRenderAction::class, fn () => new HistoryRenderAction(
            $container->get(ConfigInterface::class),
            $container->get(PermitService::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
        ));

        // History Factory
        $container->bind(HistoryActionFactory::class, fn () => new HistoryActionFactory(
            $container,
        ));

        // HistoryController
        $container->bind(HistoryController::class, fn (): HistoryController => new HistoryController(
            $container->get(HistoryActionFactory::class),
            $container->get(RateLimiterInterface::class),
        ));

        $container->bind(DatenschutzAction::class, fn () => new DatenschutzAction(
            $container->get(ConfigInterface::class),
            $container->get(TemplateRenderer::class),
        ));

        $container->bind(ImpressumAction::class, fn () => new ImpressumAction(
            $container->get(ConfigInterface::class),
            $container->get(TemplateRenderer::class),
        ));

        $container->bind(CapturePaymentAction::class, fn () => new CapturePaymentAction(
            $container->get(PermitService::class),
        ));

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

        $container->bind(SuccessController::class, fn (): SuccessController => new SuccessController(
            $container->get(BankQrGenerator::class),
            $container->get(ConfigInterface::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
        ));
    }
}
