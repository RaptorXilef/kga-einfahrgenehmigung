<?php return array (
  'exact' => 
  array (
    'GET|POST' => 
    array (
      '/admin_logout' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\AdminLogoutAction',
        'auth' => false,
      ),
      '/bank_import_analyze' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\BankImportAnalyzeAction',
        'auth' => false,
      ),
      '/dashboard_export' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\DashboardExportAction',
        'auth' => false,
      ),
      '/filter_dashboard' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\DashboardFilterAction',
        'auth' => false,
      ),
      '/upload_group_image' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\GroupUploadImageAction',
        'auth' => false,
      ),
      '/create_manual' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\PermitCreateManualAction',
        'auth' => false,
      ),
      '/mark_as_paid' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\PermitMarkAsPaidAction',
        'auth' => false,
      ),
      '/suspend_permit' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\PermitToggleSuspensionAction',
        'auth' => false,
      ),
      '/unsuspend_permit' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\PermitToggleSuspensionAction',
        'auth' => false,
      ),
      '/change_own_avatar' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\ProfileUploadAvatarAction',
        'auth' => false,
      ),
      '/anonymize_archive' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\SystemAnonymizeArchiveAction',
        'auth' => false,
      ),
      '/changelog' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\SystemChangelogAction',
        'auth' => false,
      ),
      '/create_backup' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\SystemCreateBackupAction',
        'auth' => false,
      ),
      '/force_update_check' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\SystemForceUpdateCheckAction',
        'auth' => false,
      ),
      '/migrate_data' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\SystemMigrateDataAction',
        'auth' => false,
      ),
      '/resend_mail' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\SystemResendMailAction',
        'auth' => false,
      ),
      '/restore_data' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\SystemRestoreDataAction',
        'auth' => false,
      ),
      '/run_update_migrations' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\SystemRunUpdateMigrationsAction',
        'auth' => false,
      ),
      '/truncate_target' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\SystemTruncateTargetAction',
        'auth' => false,
      ),
      '/delete_user' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\UserDeleteAction',
        'auth' => false,
      ),
      '/upload_avatar' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\UserUploadAvatarAction',
        'auth' => false,
      ),
      '/create_voucher' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\VoucherCreateAction',
        'auth' => false,
      ),
      '/delete_voucher' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\VoucherDeleteAction',
        'auth' => false,
      ),
      '/cron' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\System\\CronAction',
        'auth' => false,
      ),
      '/datenschutz' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\DatenschutzAction',
        'auth' => false,
      ),
      '/history_cancel_permit' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\HistoryCancelPermitAction',
        'auth' => false,
      ),
      '/history_logout' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\HistoryLogoutAction',
        'auth' => false,
      ),
      '/history_print' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\HistoryPrintAction',
        'auth' => false,
      ),
      '/history_request_link' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\HistoryRequestLinkAction',
        'auth' => false,
      ),
      '/history_submit_code' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\HistorySubmitCodeAction',
        'auth' => false,
      ),
      '/history_verify_token' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\HistoryVerifyTokenAction',
        'auth' => false,
      ),
      '/impressum' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\ImpressumAction',
        'auth' => false,
      ),
      '/permit_edit' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\PermitEditAction',
        'auth' => false,
      ),
      '/success' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\SuccessAction',
        'auth' => false,
      ),
      '/verify_render' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\VerificationRenderAction',
        'auth' => false,
      ),
      '/verify_submit' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\VerificationSubmitAction',
        'auth' => false,
      ),
    ),
    'GET' => 
    array (
      '/admin_print' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\AdminPrintAction',
        'auth' => true,
      ),
      '/admin' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\DashboardRenderAction',
        'auth' => true,
      ),
      '/profile' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\ProfileRenderAction',
        'auth' => true,
      ),
      '/users' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\UserManagementRenderAction',
        'auth' => true,
      ),
      '/api/process_mail_queue' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\System\\ProcessMailQueueAction',
        'auth' => false,
      ),
      '/admin_login' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\AdminLoginAction',
        'auth' => false,
      ),
      '/checkout' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\CheckoutAction',
        'auth' => false,
      ),
      '/check' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\CheckPermitAction',
        'auth' => false,
      ),
      '/history' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\HistoryRenderAction',
        'auth' => false,
      ),
      '/' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\PermitRenderAction',
        'auth' => false,
      ),
    ),
    'POST' => 
    array (
      '/bank_import_process' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\BankImportProcessAction',
        'auth' => true,
      ),
      '/change_own_password' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\ProfileUpdatePasswordAction',
        'auth' => false,
      ),
      '/change_own_username' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\ProfileUpdateUsernameAction',
        'auth' => false,
      ),
      '/delete_role' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\RoleDeleteAction',
        'auth' => true,
      ),
      '/rename_role' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\RoleRenameAction',
        'auth' => false,
      ),
      '/save_role' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\RoleSaveAction',
        'auth' => true,
      ),
      '/clear_cache' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\SystemClearCacheAction',
        'auth' => false,
      ),
      '/change_user_role' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\UserChangeRoleAction',
        'auth' => true,
      ),
      '/rename_user' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\UserRenameAction',
        'auth' => false,
      ),
      '/change_user_password' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\UserResetPasswordAction',
        'auth' => false,
      ),
      '/save_user' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\UserSaveAction',
        'auth' => true,
      ),
      '/activate_voucher' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\VoucherToggleAction',
        'auth' => true,
      ),
      '/deactivate_voucher' => 
      array (
        'class' => 'App\\Application\\Actions\\Admin\\VoucherToggleAction',
        'auth' => true,
      ),
      '/api/search_permits' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\Admin\\SearchPermitsAction',
        'auth' => true,
      ),
      '/api/capture' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\Frontend\\CapturePaymentAction',
        'auth' => false,
      ),
      '/api/create_order' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\Frontend\\CreateOrderAction',
        'auth' => false,
      ),
      '/api/finalize_wire' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\Frontend\\FinalizeWireAction',
        'auth' => false,
      ),
      '/api/ping' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\Shared\\ExtendSessionAction',
        'auth' => false,
      ),
      '/api/get_date_info' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\Shared\\GetDateInfoAction',
        'auth' => false,
      ),
      '/api/get_template_price' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\Shared\\GetTemplatePriceAction',
        'auth' => false,
      ),
      '/api/check_update' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\System\\CheckUpdateAction',
        'auth' => true,
      ),
      '/finalize_update' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\System\\FinalizeUpdateAction',
        'auth' => false,
      ),
      '/api/perform_update' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\System\\PerformUpdateAction',
        'auth' => true,
      ),
      '/api/process_mail_queue' => 
      array (
        'class' => 'App\\Application\\Actions\\Api\\System\\ProcessMailQueueAction',
        'auth' => false,
      ),
      '/admin_login' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\AdminLoginAction',
        'auth' => false,
      ),
      '/' => 
      array (
        'class' => 'App\\Application\\Actions\\Frontend\\PermitSubmitAction',
        'auth' => false,
      ),
    ),
  ),
  'dynamic' => 
  array (
  ),
);