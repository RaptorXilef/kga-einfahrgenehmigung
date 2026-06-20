<?php

declare(strict_types=1);

namespace App\Bootstrap\Providers;

use App\Application\Actions\AdminActionFactory;
use App\Application\Actions\AdminLoginAction;
use App\Application\Actions\AdminLogoutAction;
use App\Application\Actions\AdminPrintAction;
use App\Application\Actions\ApiActionFactory;
use App\Application\Actions\ApiGetDateInfoAction;
use App\Application\Actions\ApiGetTemplatePriceAction;
use App\Application\Actions\ApiSearchPermitsAction;
use App\Application\Actions\CapturePaymentAction;
use App\Application\Actions\CheckoutAction;
use App\Application\Actions\CheckoutCreateOrderAction;
use App\Application\Actions\CheckoutFinalizeWireAction;
use App\Application\Actions\CheckPermitAction;
use App\Application\Actions\DashboardExportAction;
use App\Application\Actions\DashboardFilterAction;
use App\Application\Actions\DashboardRenderAction;
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
use App\Application\Actions\SystemChangelogAction;
use App\Application\Actions\SystemCheckUpdateAction;
use App\Application\Actions\SystemClearCacheAction;
use App\Application\Actions\SystemCronAction;
use App\Application\Actions\SystemFinalizeUpdateAction;
use App\Application\Actions\SystemMigrateDataAction;
use App\Application\Actions\SystemPerformUpdateAction;
use App\Application\Actions\SystemProcessMailQueueAction;
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
use App\Application\ApiController;
use App\Application\ChangelogController;
use App\Application\CronController;
use App\Application\FrontendController;
use App\Application\HistoryController;
use App\Application\Middleware\AdminAuthGuardMiddleware;
use App\Application\Middleware\AnalyticsMiddleware;
use App\Application\Middleware\ApiCsrfMiddleware;
use App\Application\Middleware\TerminateMailQueueMiddleware;
use App\Application\PermitController;
use App\Application\Session\SessionManager;
use App\Application\UserController;
use App\Application\VerificationController;
use App\Application\View\TemplateRenderer;
use App\Bootstrap\Container;
use App\Contracts\Bootstrap\ServiceProviderInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Event\EventDispatcherInterface;
use App\Contracts\Mail\MailLogInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Payment\PaymentProviderInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Contracts\Storage\BackupServiceInterface;
use App\Contracts\Storage\GroupRepositoryInterface;
use App\Contracts\Storage\PermitArchiveRepositoryInterface;
use App\Contracts\Storage\StorageInterface;
use App\Contracts\Storage\UserRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Service\AuthService;
use App\Core\Service\BankQrGenerator;
use App\Core\Service\ExportService;
use App\Core\Service\HolidayService;
use App\Core\Service\ImageStorageService;
use App\Core\Service\MagicLinkService;
use App\Core\Service\Maintenance\CronScheduler;
use App\Core\Service\PermitFilterService;
use App\Core\Service\PermitService;
use App\Core\Service\ReportingService;
use App\Core\Service\SystemInfoService;
use App\Core\Service\VoucherService;
use App\Infrastructure\Maintenance\GitHubUpdaterService;
use App\Infrastructure\Maintenance\MigrationService;
use App\Infrastructure\Maintenance\StorageBootstrapper;
use App\Infrastructure\Maintenance\UpdateMigrationService;

/**
 * Registriert sämtliche Controller und View-Renderer der Application.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final class ControllerServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // --- 3.1 View Renderer ---
        $container->bind(TemplateRenderer::class, fn (): TemplateRenderer => new TemplateRenderer(
            $container->get(ConfigInterface::class),
            $container->get(ImageStorageService::class),
        ));

        // --- 3.x Session and Auth ---
        $container->bind(SessionManager::class, fn () => new SessionManager());

        $container->bind(AuthService::class, fn (): AuthService => new AuthService(
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(RateLimiterInterface::class),
            $container->get(SessionManager::class),
            $container->get(UserRepositoryInterface::class),
        ));

        // --- Middleware ---
        $container->bind(ApiCsrfMiddleware::class, fn () => new ApiCsrfMiddleware(
            $container->get(SessionManager::class),
        ));
        $container->bind(AdminAuthGuardMiddleware::class, fn () => new AdminAuthGuardMiddleware(
            $container->get(AuthService::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(TerminateMailQueueMiddleware::class, fn () => new TerminateMailQueueMiddleware(
            $container->get(MailServiceInterface::class),
        ));
        $container->bind(AnalyticsMiddleware::class, fn () => new AnalyticsMiddleware(
            $container->get(ConfigInterface::class),
            $container->get(SessionManager::class),
        ));

        // -- Services ---
        $container->bind(ImageStorageService::class, fn () => new ImageStorageService(
            $container->get(ConfigInterface::class),
        ));
        $container->bind(PermitFilterService::class, fn (): PermitFilterService => new PermitFilterService(
            $container->get(ConfigInterface::class),
            $container->get(StorageInterface::class),
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
        $container->bind(AdminPrintAction::class, fn () => new AdminPrintAction(
            $container->get(AuthService::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(HolidayService::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(DashboardExportAction::class, fn () => new DashboardExportAction(
            $container->get(ExportService::class),
            $container->get(PermitFilterService::class),
            $container->get(SessionManager::class),
        ));
        $container->bind(DashboardFilterAction::class, fn () => new DashboardFilterAction(
            $container->get(SessionManager::class),
        ));
        $container->bind(PermitCreateManualAction::class, fn () => new PermitCreateManualAction(
            $container->get(PermitService::class),
        ));
        $container->bind(PermitMarkAsPaidAction::class, fn () => new PermitMarkAsPaidAction(
            $container->get(PermitService::class),
        ));
        $container->bind(PermitToggleSuspensionAction::class, fn () => new PermitToggleSuspensionAction(
            $container->get(PermitService::class),
        ));
        $container->bind(SystemAnonymizeArchiveAction::class, fn () => new SystemAnonymizeArchiveAction(
            $container->get(PermitArchiveRepositoryInterface::class),
        ));
        $container->bind(SystemClearCacheAction::class, fn () => new SystemClearCacheAction(
            $container->get(MigrationService::class),
        ));
        $container->bind(SystemMigrateDataAction::class, fn () => new SystemMigrateDataAction(
            $container->get(MigrationService::class),
        ));
        $container->bind(SystemResendMailAction::class, fn () => new SystemResendMailAction(
            $container->get(MailLogInterface::class),
            $container->get(MailServiceInterface::class),
        ));
        $container->bind(SystemRestoreDataAction::class, fn () => new SystemRestoreDataAction(
            $container->get(MigrationService::class),
        ));
        $container->bind(SystemTruncateTargetAction::class, fn () => new SystemTruncateTargetAction(
            $container->get(MigrationService::class),
        ));
        $container->bind(VoucherCreateAction::class, fn () => new VoucherCreateAction(
            $container->get(AuthService::class),
            $container->get(VoucherService::class),
        ));
        $container->bind(VoucherDeleteAction::class, fn () => new VoucherDeleteAction(
            $container->get(VoucherService::class),
        ));
        $container->bind(VoucherToggleAction::class, fn () => new VoucherToggleAction(
            $container->get(VoucherService::class),
        ));

        // Admin Factory
        $container->bind(AdminActionFactory::class, fn () => new AdminActionFactory(
            $container,
        ));

        // Admin Controller
        $container->bind(AdminController::class, fn (): AdminController => new AdminController(
            $container->get(AdminActionFactory::class),
            $container->get(AdminAuthGuardMiddleware::class),
            $container->get(AnalyticsMiddleware::class),
            $container->get(AuthService::class),
            $container->get(BackupServiceInterface::class),
            $container->get(CronScheduler::class),
            $container->get(SessionManager::class),
            $container->get(StorageBootstrapper::class),
            $container->get(StorageInterface::class),
        ));

        $container->bind(DashboardRenderAction::class, fn () => new DashboardRenderAction(
            $container->get(AuthService::class),
            $container->get(BackupServiceInterface::class),
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(MailLogInterface::class),
            $container->get(PermitFilterService::class),
            $container->get(PermitService::class),
            $container->get(ReportingService::class),
            $container->get(SessionManager::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
            $container->get(UserRepositoryInterface::class),
            $container->get(VoucherRepositoryInterface::class),
            $container->get(VoucherService::class),
        ));

        // Group Actions
        $container->bind(GroupDeleteAction::class, fn () => new GroupDeleteAction(
            $container->get(ConfigInterface::class),
            $container->get(GroupRepositoryInterface::class),
        ));
        $container->bind(GroupRenameAction::class, fn () => new GroupRenameAction(
            $container->get(GroupRepositoryInterface::class),
        ));
        $container->bind(GroupSaveAction::class, fn () => new GroupSaveAction(
            $container->get(AuthService::class),
            $container->get(GroupRepositoryInterface::class),
            $container->get(ImageStorageService::class),
        ));
        $container->bind(GroupUploadImageAction::class, fn () => new GroupUploadImageAction(
            $container->get(ImageStorageService::class),
        ));

        // User Actions
        $container->bind(UserChangeGroupAction::class, fn () => new UserChangeGroupAction(
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(UserDeleteAction::class, fn () => new UserDeleteAction(
            $container->get(AuthService::class),
            $container->get(ConfigInterface::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(UserRenameAction::class, fn () => new UserRenameAction(
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(UserResetPasswordAction::class, fn () => new UserResetPasswordAction(
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(UserSaveAction::class, fn () => new UserSaveAction(
            $container->get(AuthService::class),
            $container->get(UserRepositoryInterface::class),
            $container->get(ImageStorageService::class),
        ));
        $container->bind(UserUploadAvatarAction::class, fn () => new UserUploadAvatarAction(
            $container->get(ImageStorageService::class),
        ));

        // Profile Actions
        $container->bind(ProfileUpdatePasswordAction::class, fn () => new ProfileUpdatePasswordAction(
            $container->get(AuthService::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(ProfileUpdateUsernameAction::class, fn () => new ProfileUpdateUsernameAction(
            $container->get(AuthService::class),
            $container->get(SessionManager::class),
            $container->get(UserRepositoryInterface::class),
        ));
        $container->bind(ProfileUploadAvatarAction::class, fn () => new ProfileUploadAvatarAction(
            $container->get(AuthService::class),
            $container->get(ImageStorageService::class),
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
            $container->get(AnalyticsMiddleware::class),
            $container->get(AuthService::class),
            $container->get(SessionManager::class),
            $container->get(TerminateMailQueueMiddleware::class),
            $container->get(UserActionFactory::class),
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
            $container->get(HolidayService::class),
            $container->get(PermitService::class),
            $container->get(TemplateRenderer::class),
        ));

        // History Actions
        $container->bind(HistoryLogoutAction::class, fn () => new HistoryLogoutAction());
        $container->bind(HistoryRequestLinkAction::class, fn () => new HistoryRequestLinkAction(
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
            $container->get(SessionManager::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
        ));
        $container->bind(HistoryRenderAction::class, fn () => new HistoryRenderAction(
            $container->get(ConfigInterface::class),
            $container->get(PermitService::class),
            $container->get(SessionManager::class),
            $container->get(StorageInterface::class),
            $container->get(TemplateRenderer::class),
        ));

        // History Factory
        $container->bind(HistoryActionFactory::class, fn () => new HistoryActionFactory(
            $container,
        ));

        // History Controller
        $container->bind(HistoryController::class, fn (): HistoryController => new HistoryController(
            $container->get(AnalyticsMiddleware::class),
            $container->get(HistoryActionFactory::class),
            $container->get(RateLimiterInterface::class),
            $container->get(SessionManager::class),
            $container->get(TerminateMailQueueMiddleware::class),
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
            $container->get(PaymentProviderInterface::class),
        ));

        // Permit Actions
        $container->bind(PermitEditAction::class, fn () => new PermitEditAction(
            $container->get(PermitService::class),
            $container->get(SessionManager::class),
        ));
        $container->bind(PermitRenderAction::class, fn () => new PermitRenderAction(
            $container->get(ConfigInterface::class),
            $container->get(SessionManager::class),
            $container->get(TemplateRenderer::class),
            $container->get(VoucherRepositoryInterface::class),
            $container->get(VoucherService::class),
        ));

        $container->bind(PermitSubmitAction::class, fn () => new PermitSubmitAction(
            $container->get(PermitService::class),
            $container->get(SessionManager::class),
        ));

        // Permit Factory
        $container->bind(PermitActionFactory::class, fn () => new PermitActionFactory(
            $container,
        ));

        // Permit Controller
        $container->bind(PermitController::class, fn (): PermitController => new PermitController(
            $container->get(AnalyticsMiddleware::class),
            $container->get(PermitActionFactory::class),
            $container->get(SessionManager::class),
            $container->get(TerminateMailQueueMiddleware::class),
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
            $container->get(AnalyticsMiddleware::class),
            $container->get(RateLimiterInterface::class),
            $container->get(SessionManager::class),
            $container->get(TerminateMailQueueMiddleware::class),
            $container->get(VerificationActionFactory::class),
        ));

        // API Actions
        $container->bind(SystemCheckUpdateAction::class, fn () => new SystemCheckUpdateAction(
            $container->get(GitHubUpdaterService::class),
            $container->get(SystemInfoService::class),
        ));
        $container->bind(CheckoutCreateOrderAction::class, fn () => new CheckoutCreateOrderAction(
            $container->get(PermitService::class),
            $container->get(PaymentProviderInterface::class),
        ));
        $container->bind(SystemFinalizeUpdateAction::class, fn () => new SystemFinalizeUpdateAction(
            $container->get(UpdateMigrationService::class),
            $container->get(AuthService::class),
        ));
        $container->bind(CheckoutFinalizeWireAction::class, fn () => new CheckoutFinalizeWireAction(
            $container->get(PermitService::class),
        ));
        $container->bind(ApiGetDateInfoAction::class, fn () => new ApiGetDateInfoAction(
            $container->get(HolidayService::class),
        ));
        $container->bind(ApiGetTemplatePriceAction::class, fn () => new ApiGetTemplatePriceAction(
            $container->get(ConfigInterface::class),
            $container->get(PermitService::class),
            $container->get(RateLimiterInterface::class),
            $container->get(VoucherRepositoryInterface::class),
            $container->get(VoucherService::class),
        ));
        $container->bind(SystemPerformUpdateAction::class, fn () => new SystemPerformUpdateAction(
            $container->get(GitHubUpdaterService::class),
        ));
        $container->bind(SystemProcessMailQueueAction::class, fn () => new SystemProcessMailQueueAction(
            $container->get(MailServiceInterface::class),
        ));
        $container->bind(ApiSearchPermitsAction::class, fn () => new ApiSearchPermitsAction(
            $container->get(PermitService::class),
        ));

        // API Factory
        $container->bind(ApiActionFactory::class, fn () => new ApiActionFactory(
            $container,
        ));

        // API Controller
        $container->bind(ApiController::class, fn () => new ApiController(
            $container->get(ApiActionFactory::class),
            $container->get(AuthService::class),
            $container->get(RateLimiterInterface::class),
            $container->get(SessionManager::class),
        ));

        $container->bind(FrontendController::class, fn () => new FrontendController(
            $container->get(AnalyticsMiddleware::class),
            $container->get(TerminateMailQueueMiddleware::class),
        ));
        $container->bind(SystemCronAction::class, fn () => new SystemCronAction(
            $container->get(CronScheduler::class),
        ));
        $container->bind(CronController::class, fn () => new CronController(
            $container->get(ConfigInterface::class),
            $container->get(SystemCronAction::class),
        ));
        $container->bind(SystemChangelogAction::class, fn () => new SystemChangelogAction(
            $container->get(AuthService::class),
            $container->get(TemplateRenderer::class),
            $container->get(SystemInfoService::class),
        ));
        $container->bind(ChangelogController::class, fn () => new ChangelogController(
            $container->get(AnalyticsMiddleware::class),
            $container->get(AuthService::class),
            $container->get(SystemChangelogAction::class),
            $container->get(TerminateMailQueueMiddleware::class),
        ));
    }
}
