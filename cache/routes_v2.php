<?php return array (
  'exact' => 
  array (
    'GET' => 
    array (
      '/bank_import_analyze' => 
      array (
        'class' => 'App\\Modules\\Finance\\Application\\UseCases\\AnalyzeBankImport\\AnalyzeBankImportAction',
        'auth' => false,
      ),
      '/dashboard_export' => 
      array (
        'class' => 'App\\Modules\\Finance\\Application\\UseCases\\ExportFinanceData\\ExportFinanceDataAction',
        'auth' => false,
      ),
      '/admin_login' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\AuthenticateAdmin\\AdminLoginAction',
        'auth' => false,
      ),
      '/admin_logout' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\AuthenticateAdmin\\AdminLogoutAction',
        'auth' => false,
      ),
      '/profile' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageProfile\\ProfileRenderAction',
        'auth' => true,
      ),
      '/change_own_avatar' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageProfile\\ProfileUploadAvatarAction',
        'auth' => false,
      ),
      '/upload_role_image' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageRoles\\RoleUploadImageAction',
        'auth' => false,
      ),
      '/delete_user' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageUsers\\UserDeleteAction',
        'auth' => false,
      ),
      '/users' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageUsers\\UserManagementRenderAction',
        'auth' => true,
      ),
      '/upload_avatar' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageUsers\\UserUploadAvatarAction',
        'auth' => false,
      ),
      '/history_request_link' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\RequestMagicLink\\HistoryRequestLinkAction',
        'auth' => false,
      ),
      '/history_logout' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\VerifyMagicLink\\HistoryLogoutAction',
        'auth' => false,
      ),
      '/history_submit_code' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\VerifyMagicLink\\HistorySubmitCodeAction',
        'auth' => false,
      ),
      '/history_verify_token' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\VerifyMagicLink\\HistoryVerifyTokenAction',
        'auth' => false,
      ),
      '/api/cron/archive' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\ArchiveExpiredPermits\\ArchiveCronAction',
        'auth' => false,
      ),
      '/history_cancel_permit' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\CancelPermit\\HistoryCancelPermitAction',
        'auth' => false,
      ),
      '/check' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\CheckPermit\\CheckPermitAction',
        'auth' => false,
      ),
      '/verify' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\ConfirmPermitEmail\\VerificationAction',
        'auth' => false,
      ),
      '/create_manual' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\CreateManualPermit\\PermitCreateManualAction',
        'auth' => false,
      ),
      '/success' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\FinalizePermit\\SuccessAction',
        'auth' => false,
      ),
      '/history' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\GetPermitHistory\\HistoryRenderAction',
        'auth' => false,
      ),
      '/checkout' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\GetVerifiedRequest\\CheckoutAction',
        'auth' => false,
      ),
      '/permit_edit' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\GetVerifiedRequest\\PermitEditAction',
        'auth' => false,
      ),
      '/mark_as_paid' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\MarkPermitAsPaid\\PermitMarkAsPaidAction',
        'auth' => false,
      ),
      '/admin_print' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\PrintPermit\\AdminPrintAction',
        'auth' => true,
      ),
      '/history_print' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\PrintPermit\\HistoryPrintAction',
        'auth' => false,
      ),
      '/api/cron/reminders' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\SendPaymentReminders\\RemindersCronAction',
        'auth' => false,
      ),
      '/' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\SubmitPermitRequest\\PermitRenderAction',
        'auth' => false,
      ),
      '/suspend_permit' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\TogglePermitSuspension\\TogglePermitSuspensionAction',
        'auth' => false,
      ),
      '/unsuspend_permit' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\TogglePermitSuspension\\TogglePermitSuspensionAction',
        'auth' => false,
      ),
      '/anonymize_archive' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\Maintenance\\AnonymizeArchiveAction',
        'auth' => false,
      ),
      '/run_update_migrations' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\Maintenance\\RunUpdateMigrationsAction',
        'auth' => false,
      ),
      '/api/system_update' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\Maintenance\\SystemUpdateAction',
        'auth' => false,
      ),
      '/api/cron/backup' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageBackups\\BackupCronAction',
        'auth' => false,
      ),
      '/create_backup' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageBackups\\CreateBackupAction',
        'auth' => false,
      ),
      '/truncate_target' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageBackups\\TruncateTargetAction',
        'auth' => false,
      ),
      '/api/process_mail_queue' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageMails\\ProcessMailQueueAction',
        'auth' => false,
      ),
      '/resend_mail' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageMails\\ResendMailAction',
        'auth' => false,
      ),
      '/api/cron/spam_sync' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageMails\\SpamSyncCronAction',
        'auth' => false,
      ),
      '/debug_mail' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageMails\\ViewDebugMailAction',
        'auth' => true,
      ),
      '/api/qr.png' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\QrCode\\QrCodeRenderAction',
        'auth' => false,
      ),
      '/changelog' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\SystemInfo\\ViewChangelogAction',
        'auth' => true,
      ),
      '/filter_dashboard' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ViewDashboard\\DashboardFilterAction',
        'auth' => false,
      ),
      '/admin' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ViewDashboard\\DashboardRenderAction',
        'auth' => true,
      ),
      '/datenschutz' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ViewLegal\\DatenschutzAction',
        'auth' => false,
      ),
      '/impressum' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ViewLegal\\ImpressumAction',
        'auth' => false,
      ),
      '/create_voucher' => 
      array (
        'class' => 'App\\Modules\\Voucher\\Application\\UseCases\\CreateVoucher\\CreateVoucherAction',
        'auth' => false,
      ),
      '/delete_voucher' => 
      array (
        'class' => 'App\\Modules\\Voucher\\Application\\UseCases\\DeleteVoucher\\DeleteVoucherAction',
        'auth' => false,
      ),
    ),
    'POST' => 
    array (
      '/bank_import_analyze' => 
      array (
        'class' => 'App\\Modules\\Finance\\Application\\UseCases\\AnalyzeBankImport\\AnalyzeBankImportAction',
        'auth' => false,
      ),
      '/dashboard_export' => 
      array (
        'class' => 'App\\Modules\\Finance\\Application\\UseCases\\ExportFinanceData\\ExportFinanceDataAction',
        'auth' => false,
      ),
      '/dismiss_transfer' => 
      array (
        'class' => 'App\\Modules\\Finance\\Application\\UseCases\\ProcessBankImport\\DismissTransferAction',
        'auth' => false,
      ),
      '/bank_import_process' => 
      array (
        'class' => 'App\\Modules\\Finance\\Application\\UseCases\\ProcessBankImport\\ProcessBankImportAction',
        'auth' => true,
      ),
      '/admin_login' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\AuthenticateAdmin\\AdminLoginAction',
        'auth' => false,
      ),
      '/admin_logout' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\AuthenticateAdmin\\AdminLogoutAction',
        'auth' => false,
      ),
      '/api/ping' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\AuthenticateAdmin\\ExtendSessionAction',
        'auth' => false,
      ),
      '/change_own_password' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageProfile\\ProfileUpdatePasswordAction',
        'auth' => false,
      ),
      '/change_own_username' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageProfile\\ProfileUpdateUsernameAction',
        'auth' => false,
      ),
      '/change_own_avatar' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageProfile\\ProfileUploadAvatarAction',
        'auth' => false,
      ),
      '/delete_role' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageRoles\\RoleDeleteAction',
        'auth' => true,
      ),
      '/rename_role' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageRoles\\RoleRenameAction',
        'auth' => false,
      ),
      '/save_role' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageRoles\\RoleSaveAction',
        'auth' => true,
      ),
      '/upload_role_image' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageRoles\\RoleUploadImageAction',
        'auth' => false,
      ),
      '/api/mark_changelog_read' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageUsers\\MarkChangelogReadAction',
        'auth' => true,
      ),
      '/change_user_role' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageUsers\\UserChangeRoleAction',
        'auth' => true,
      ),
      '/delete_user' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageUsers\\UserDeleteAction',
        'auth' => false,
      ),
      '/rename_user' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageUsers\\UserRenameAction',
        'auth' => false,
      ),
      '/change_user_password' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageUsers\\UserResetPasswordAction',
        'auth' => false,
      ),
      '/save_user' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageUsers\\UserSaveAction',
        'auth' => true,
      ),
      '/upload_avatar' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\ManageUsers\\UserUploadAvatarAction',
        'auth' => false,
      ),
      '/history_request_link' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\RequestMagicLink\\HistoryRequestLinkAction',
        'auth' => false,
      ),
      '/history_logout' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\VerifyMagicLink\\HistoryLogoutAction',
        'auth' => false,
      ),
      '/history_submit_code' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\VerifyMagicLink\\HistorySubmitCodeAction',
        'auth' => false,
      ),
      '/history_verify_token' => 
      array (
        'class' => 'App\\Modules\\Identity\\Application\\UseCases\\VerifyMagicLink\\HistoryVerifyTokenAction',
        'auth' => false,
      ),
      '/api/cron/archive' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\ArchiveExpiredPermits\\ArchiveCronAction',
        'auth' => false,
      ),
      '/history_cancel_permit' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\CancelPermit\\HistoryCancelPermitAction',
        'auth' => false,
      ),
      '/verify' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\ConfirmPermitEmail\\VerificationAction',
        'auth' => false,
      ),
      '/create_manual' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\CreateManualPermit\\PermitCreateManualAction',
        'auth' => false,
      ),
      '/api/capture' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\FinalizePermit\\CapturePaymentAction',
        'auth' => false,
      ),
      '/api/create_order' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\FinalizePermit\\CreateOrderAction',
        'auth' => false,
      ),
      '/api/finalize_wire' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\FinalizePermit\\FinalizeWireAction',
        'auth' => false,
      ),
      '/success' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\FinalizePermit\\SuccessAction',
        'auth' => false,
      ),
      '/api/get_date_info' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\GetDateInfo\\GetDateInfoAction',
        'auth' => false,
      ),
      '/api/get_template_price' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\GetTemplatePrice\\GetTemplatePriceAction',
        'auth' => false,
      ),
      '/permit_edit' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\GetVerifiedRequest\\PermitEditAction',
        'auth' => false,
      ),
      '/mark_as_paid' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\MarkPermitAsPaid\\PermitMarkAsPaidAction',
        'auth' => false,
      ),
      '/history_print' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\PrintPermit\\HistoryPrintAction',
        'auth' => false,
      ),
      '/api/search_permits' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\SearchPermits\\SearchPermitsAction',
        'auth' => true,
      ),
      '/send_reminder' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\SendPaymentReminders\\PermitSendReminderAction',
        'auth' => false,
      ),
      '/api/cron/reminders' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\SendPaymentReminders\\RemindersCronAction',
        'auth' => false,
      ),
      '/' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\SubmitPermitRequest\\SubmitPermitAction',
        'auth' => false,
      ),
      '/suspend_permit' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\TogglePermitSuspension\\TogglePermitSuspensionAction',
        'auth' => false,
      ),
      '/unsuspend_permit' => 
      array (
        'class' => 'App\\Modules\\Permit\\Application\\UseCases\\TogglePermitSuspension\\TogglePermitSuspensionAction',
        'auth' => false,
      ),
      '/anonymize_archive' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\Maintenance\\AnonymizeArchiveAction',
        'auth' => false,
      ),
      '/clear_cache' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\Maintenance\\ClearCacheAction',
        'auth' => false,
      ),
      '/run_update_migrations' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\Maintenance\\RunUpdateMigrationsAction',
        'auth' => false,
      ),
      '/api/system_update' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\Maintenance\\SystemUpdateAction',
        'auth' => false,
      ),
      '/api/cron/backup' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageBackups\\BackupCronAction',
        'auth' => false,
      ),
      '/create_backup' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageBackups\\CreateBackupAction',
        'auth' => false,
      ),
      '/restore_data' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageBackups\\RestoreDataAction',
        'auth' => false,
      ),
      '/truncate_target' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageBackups\\TruncateTargetAction',
        'auth' => false,
      ),
      '/api/process_mail_queue' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageMails\\ProcessMailQueueAction',
        'auth' => false,
      ),
      '/resend_mail' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageMails\\ResendMailAction',
        'auth' => false,
      ),
      '/api/cron/spam_sync' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ManageMails\\SpamSyncCronAction',
        'auth' => false,
      ),
      '/changelog' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\SystemInfo\\ViewChangelogAction',
        'auth' => true,
      ),
      '/filter_dashboard' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ViewDashboard\\DashboardFilterAction',
        'auth' => false,
      ),
      '/datenschutz' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ViewLegal\\DatenschutzAction',
        'auth' => false,
      ),
      '/impressum' => 
      array (
        'class' => 'App\\Modules\\System\\Application\\UseCases\\ViewLegal\\ImpressumAction',
        'auth' => false,
      ),
      '/create_voucher' => 
      array (
        'class' => 'App\\Modules\\Voucher\\Application\\UseCases\\CreateVoucher\\CreateVoucherAction',
        'auth' => false,
      ),
      '/delete_voucher' => 
      array (
        'class' => 'App\\Modules\\Voucher\\Application\\UseCases\\DeleteVoucher\\DeleteVoucherAction',
        'auth' => false,
      ),
      '/activate_voucher' => 
      array (
        'class' => 'App\\Modules\\Voucher\\Application\\UseCases\\ToggleVoucher\\ToggleVoucherAction',
        'auth' => true,
      ),
      '/deactivate_voucher' => 
      array (
        'class' => 'App\\Modules\\Voucher\\Application\\UseCases\\ToggleVoucher\\ToggleVoucherAction',
        'auth' => true,
      ),
    ),
  ),
  'dynamic' => 
  array (
  ),
);