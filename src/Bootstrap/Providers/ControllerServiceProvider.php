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
use App\Application\Actions\PermitActionFactory;
use App\Application\Actions\PermitEditAction;
use App\Application\Actions\PermitRenderAction;
use App\Application\Actions\PermitSubmitAction;
use App\Application\Actions\ResendMailAction;
use App\Application\Actions\RestoreDataAction;
use App\Application\Actions\SuccessAction;
use App\Application\Actions\ToggleSuspensionAction;
use App\Application\Actions\ToggleVoucherAction;
use App\Application\Actions\TruncateTargetAction;
use App\Application\AdminController;
use App\Application\HistoryController;
use App\Application\PermitController;
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

        // User & Group Actions
        $container->bind(\App\Application\Actions\GroupDeleteAction::class, fn () => new \App\Application\Actions\GroupDeleteAction($container->get(AuthService::class), $container->get(ConfigInterface::class), $container->get(GroupRepositoryInterface::class)));
        $container->bind(\App\Application\Actions\GroupRenameAction::class, fn () => new \App\Application\Actions\GroupRenameAction($container->get(AuthService::class), $container->get(GroupRepositoryInterface::class)));
        $container->bind(\App\Application\Actions\GroupSaveAction::class, fn () => new \App\Application\Actions\GroupSaveAction($container->get(AuthService::class), $container->get(GroupRepositoryInterface::class)));
        $container->bind(\App\Application\Actions\GroupUploadImageAction::class, fn () => new \App\Application\Actions\GroupUploadImageAction($container->get(AuthService::class), $container->get(GroupRepositoryInterface::class)));

        $container->bind(\App\Application\Actions\UserChangeGroupAction::class, fn () => new \App\Application\Actions\UserChangeGroupAction($container->get(AuthService::class), $container->get(UserRepositoryInterface::class)));
        $container->bind(\App\Application\Actions\UserDeleteAction::class, fn () => new \App\Application\Actions\UserDeleteAction($container->get(AuthService::class), $container->get(ConfigInterface::class), $container->get(UserRepositoryInterface::class)));
        $container->bind(\App\Application\Actions\UserRenameAction::class, fn () => new \App\Application\Actions\UserRenameAction($container->get(AuthService::class), $container->get(UserRepositoryInterface::class)));
        $container->bind(\App\Application\Actions\UserResetPasswordAction::class, fn () => new \App\Application\Actions\UserResetPasswordAction($container->get(AuthService::class), $container->get(UserRepositoryInterface::class)));
        $container->bind(\App\Application\Actions\UserSaveAction::class, fn () => new \App\Application\Actions\UserSaveAction($container->get(AuthService::class), $container->get(UserRepositoryInterface::class)));
        $container->bind(\App\Application\Actions\UserUploadAvatarAction::class, fn () => new \App\Application\Actions\UserUploadAvatarAction($container->get(AuthService::class), $container->get(UserRepositoryInterface::class)));

        // Profile Actions
        $container->bind(\App\Application\Actions\ProfileUpdatePasswordAction::class, fn () => new \App\Application\Actions\ProfileUpdatePasswordAction($container->get(UserRepositoryInterface::class)));
        $container->bind(\App\Application\Actions\ProfileUpdateUsernameAction::class, fn () => new \App\Application\Actions\ProfileUpdateUsernameAction($container->get(UserRepositoryInterface::class)));
        $container->bind(\App\Application\Actions\ProfileUploadAvatarAction::class, fn () => new \App\Application\Actions\ProfileUploadAvatarAction($container->get(UserRepositoryInterface::class)));

        // Render Actions
        $container->bind(\App\Application\Actions\ProfileRenderAction::class, fn () => new \App\Application\Actions\ProfileRenderAction($container->get(AuthService::class), $container->get(GroupRepositoryInterface::class), $container->get(TemplateRenderer::class), $container->get(UserRepositoryInterface::class)));
        $container->bind(\App\Application\Actions\UserManagementRenderAction::class, fn () => new \App\Application\Actions\UserManagementRenderAction($container->get(AuthService::class), $container->get(ConfigInterface::class), $container->get(GroupRepositoryInterface::class), $container->get(TemplateRenderer::class), $container->get(UserRepositoryInterface::class)));

        // Factory & Controller
        $container->bind(\App\Application\Actions\UserActionFactory::class, fn () => new \App\Application\Actions\UserActionFactory($container));

        $container->bind(UserController::class, fn (): UserController => new UserController(
            $container->get(\App\Application\Actions\UserActionFactory::class),
            $container->get(AuthService::class),
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

        // Permit Actions
        $container->bind(PermitEditAction::class, fn () => new PermitEditAction(
            $container->get(PermitService::class),
        ));

        $container->bind(PermitRenderAction::class, fn () => new PermitRenderAction(
            $container->get(ConfigInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(VoucherRepositoryInterface::class),
            $container->get(VoucherService::class),
        ));

        $container->bind(PermitSubmitAction::class, fn () => new PermitSubmitAction(
            $container->get(PermitService::class),
            $container->get(VerificationRepositoryInterface::class),
        ));

        // Permit Factory
        $container->bind(PermitActionFactory::class, fn () => new PermitActionFactory($container));

        // PermitController
        $container->bind(PermitController::class, fn (): PermitController => new PermitController(
            $container->get(PermitActionFactory::class),
        ));

        $container->bind(SuccessAction::class, fn () => new SuccessAction(
            $container->get(BankQrGenerator::class),
            $container->get(ConfigInterface::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
        ));

        $container->bind(VerificationController::class, fn (): VerificationController => new VerificationController(
            $container->get(ConfigInterface::class),
            $container->get(MailServiceInterface::class),
            $container->get(PermitService::class),
            $container->get(RateLimiterInterface::class),
            $container->get(TemplateRenderer::class),
        ));
    }
}
