<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Application\Actions\AdminActionFactory;
use App\Application\Actions\AdminLoginAction;
use App\Application\Actions\AdminLogoutAction;
use App\Application\Actions\CapturePaymentAction;
use App\Application\Actions\CheckoutAction;
use App\Application\Actions\CheckPermitAction;
use App\Application\Actions\DashboardFilterAction;
use App\Application\Actions\DatenschutzAction;
use App\Application\Actions\GroupDeleteAction;
use App\Application\Actions\GroupRenameAction;
use App\Application\Actions\GroupSaveAction;
use App\Application\Actions\GroupUploadImageAction;
use App\Application\Actions\HistoryActionFactory;
use App\Application\Actions\HistoryLogoutAction;
use App\Application\Actions\HistoryPrintAction;
use App\Application\Actions\HistoryRenderAction;
use App\Application\Actions\HistoryRequestLinkAction;
use App\Application\Actions\HistorySubmitCodeAction;
use App\Application\Actions\HistoryVerifyTokenAction;
use App\Application\Actions\ImpressumAction;
use App\Application\Actions\PermitActionFactory;
use App\Application\Actions\PermitCreateManualAction;
use App\Application\Actions\PermitEditAction;
use App\Application\Actions\PermitMarkAsPaidAction;
use App\Application\Actions\PermitRenderAction;
use App\Application\Actions\PermitSubmitAction;
use App\Application\Actions\PermitToggleSuspensionAction;
use App\Application\Actions\ProfileRenderAction;
use App\Application\Actions\ProfileUpdatePasswordAction;
use App\Application\Actions\ProfileUpdateUsernameAction;
use App\Application\Actions\ProfileUploadAvatarAction;
use App\Application\Actions\SuccessAction;
use App\Application\Actions\SystemAnonymizeArchiveAction;
use App\Application\Actions\SystemClearCacheAction;
use App\Application\Actions\SystemMigrateDataAction;
use App\Application\Actions\SystemResendMailAction;
use App\Application\Actions\SystemRestoreDataAction;
use App\Application\Actions\SystemTruncateTargetAction;
use App\Application\Actions\UserActionFactory;
use App\Application\Actions\UserChangeGroupAction;
use App\Application\Actions\UserDeleteAction;
use App\Application\Actions\UserManagementRenderAction;
use App\Application\Actions\UserRenameAction;
use App\Application\Actions\UserResetPasswordAction;
use App\Application\Actions\UserSaveAction;
use App\Application\Actions\UserUploadAvatarAction;
use App\Application\Actions\VerificationActionFactory;
use App\Application\Actions\VerificationRenderAction;
use App\Application\Actions\VerificationSubmitAction;
use App\Application\Actions\VoucherCreateAction;
use App\Application\Actions\VoucherDeleteAction;
use App\Application\Actions\VoucherToggleAction;
use App\Application\AdminController;
use App\Application\HistoryController;
use App\Application\PermitController;
use App\Application\UserController;
use App\Application\VerificationController;
use App\Application\View\TemplateRenderer;
use App\Bootstrap\Container;
use App\Contracts\Bootstrap\ServiceProviderInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
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
        $container->bind(AdminLoginAction::class, fn () => new AdminLoginAction(
            $container->get(AuthService::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(AdminLogoutAction::class, fn () => new AdminLogoutAction(
            $container->get(AuthService::class),
        ));
        $container->bind(DashboardFilterAction::class, fn () => new DashboardFilterAction());
        $container->bind(PermitCreateManualAction::class, fn () => new PermitCreateManualAction(
            $container->get(AuthService::class),
            $container->get(PermitService::class),
        ));
        $container->bind(PermitMarkAsPaidAction::class, fn () => new PermitMarkAsPaidAction(
            $container->get(AuthService::class),
            $container->get(PermitService::class),
        ));
        $container->bind(PermitToggleSuspensionAction::class, fn () => new PermitToggleSuspensionAction(
            $container->get(AuthService::class),
            $container->get(PermitService::class),
            $container->get(StorageInterface::class),
        ));
        $container->bind(SystemAnonymizeArchiveAction::class, fn () => new SystemAnonymizeArchiveAction(
            $container->get(AuthService::class),
            $container->get(PermitArchiveRepositoryInterface::class),
        ));
        $container->bind(SystemClearCacheAction::class, fn () => new SystemClearCacheAction(
            $container->get(AuthService::class),
            $container->get(MigrationService::class),
        ));
        $container->bind(SystemMigrateDataAction::class, fn () => new SystemMigrateDataAction(
            $container->get(AuthService::class),
            $container->get(MigrationService::class),
        ));
        $container->bind(SystemResendMailAction::class, fn () => new SystemResendMailAction(
            $container->get(AuthService::class),
            $container->get(MailLogInterface::class),
            $container->get(MailServiceInterface::class),
        ));
        $container->bind(SystemRestoreDataAction::class, fn () => new SystemRestoreDataAction(
            $container->get(AuthService::class),
            $container->get(MigrationService::class),
        ));
        $container->bind(SystemTruncateTargetAction::class, fn () => new SystemTruncateTargetAction(
            $container->get(AuthService::class),
            $container->get(MigrationService::class),
        ));
        $container->bind(VoucherCreateAction::class, fn () => new VoucherCreateAction(
            $container->get(AuthService::class),
            $container->get(VoucherService::class),
        ));
        $container->bind(VoucherDeleteAction::class, fn () => new VoucherDeleteAction(
            $container->get(AuthService::class),
            $container->get(VoucherService::class),
        ));
        $container->bind(VoucherToggleAction::class, fn () => new VoucherToggleAction(
            $container->get(AuthService::class),
            $container->get(VoucherService::class),
        ));

        // Admin Factory
        $container->bind(AdminActionFactory::class, fn () => new AdminActionFactory(
            $container,
        ));

        // Admin Controller
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
            $container->get(MigrationService::class),
            $container->get(PermitService::class),
            $container->get(ReportingService::class),
            $container->get(StorageBootstrapper::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
            $container->get(VoucherRepositoryInterface::class),
            $container->get(VoucherService::class),
        ));

        // Group Actions
        $container->bind(GroupDeleteAction::class, fn () => new GroupDeleteAction(
            $container->get(AuthService::class),
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
        ));
        $container->bind(GroupRenameAction::class, fn () => new GroupRenameAction(
            $container->get(AuthService::class),
            $container->get(GroupRepositoryInterface::class),
        ));
        $container->bind(GroupSaveAction::class, fn () => new GroupSaveAction(
            $container->get(AuthService::class),
            $container->get(GroupRepositoryInterface::class),
        ));
        $container->bind(GroupUploadImageAction::class, fn () => new GroupUploadImageAction(
            $container->get(AuthService::class),
            $container->get(GroupRepositoryInterface::class),
        ));

        // User Actions
        $container->bind(UserChangeGroupAction::class, fn () => new UserChangeGroupAction(
            $container->get(AuthService::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(UserDeleteAction::class, fn () => new UserDeleteAction(
            $container->get(AuthService::class),
            $container->get(ConfigInterface::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(UserRenameAction::class, fn () => new UserRenameAction(
            $container->get(AuthService::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(UserResetPasswordAction::class, fn () => new UserResetPasswordAction(
            $container->get(AuthService::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(UserSaveAction::class, fn () => new UserSaveAction(
            $container->get(AuthService::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(UserUploadAvatarAction::class, fn () => new UserUploadAvatarAction(
            $container->get(AuthService::class),
            $container->get(UserRepositoryInterface::class),
        ));

        // Profile Actions
        $container->bind(ProfileUpdatePasswordAction::class, fn () => new ProfileUpdatePasswordAction(
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(ProfileUpdateUsernameAction::class, fn () => new ProfileUpdateUsernameAction(
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(ProfileUploadAvatarAction::class, fn () => new ProfileUploadAvatarAction(
            $container->get(UserRepositoryInterface::class),
        ));

        // Render Actions
        $container->bind(ProfileRenderAction::class, fn () => new ProfileRenderAction(
            $container->get(AuthService::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(UserManagementRenderAction::class, fn () => new UserManagementRenderAction(
            $container->get(AuthService::class),
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
        ));

        // User / Profile / Group Factory
        $container->bind(UserActionFactory::class, fn () => new UserActionFactory(
            $container,
        ));

        // User / Profile / Group Controller
        $container->bind(UserController::class, fn (): UserController => new UserController(
            $container->get(UserActionFactory::class),
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
            $container->get(EventDispatcherInterface::class),
            $container->get(MagicLinkService::class),
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

        // History Controller
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
        ));

        // Permit Factory
        $container->bind(PermitActionFactory::class, fn () => new PermitActionFactory(
            $container,
        ));

        // Permit Controller
        $container->bind(PermitController::class, fn (): PermitController => new PermitController(
            $container->get(PermitActionFactory::class),
        ));
        $container->bind(SuccessAction::class, fn () => new SuccessAction(
            $container->get(BankQrGenerator::class),
            $container->get(ConfigInterface::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
        ));

        // Verification Actions
        $container->bind(VerificationRenderAction::class, fn () => new VerificationRenderAction(
            $container->get(TemplateRenderer::class),
        ));

        $container->bind(VerificationSubmitAction::class, fn () => new VerificationSubmitAction(
            $container->get(PermitService::class),
            $container->get(RateLimiterInterface::class),
        ));

        // Verification Factory
        $container->bind(VerificationActionFactory::class, fn () => new VerificationActionFactory(
            $container,
        ));

        // Verification Controller
        $container->bind(VerificationController::class, fn (): VerificationController => new VerificationController(
            $container->get(VerificationActionFactory::class),
        ));
    }
}
