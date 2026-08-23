<?php return array (
  'exact' => 
  array (
    'GET' => 
    array (
      '/admin_login' => 
      array (
        'class' => 'App\\Application\\Actions\\AdminLoginAction',
        'auth' => false,
      ),
      '/admin_print' => 
      array (
        'class' => 'App\\Application\\Actions\\AdminPrintAction',
        'auth' => true,
      ),
      '/checkout' => 
      array (
        'class' => 'App\\Application\\Actions\\CheckoutAction',
        'auth' => false,
      ),
      '/check' => 
      array (
        'class' => 'App\\Application\\Actions\\CheckPermitAction',
        'auth' => false,
      ),
      '/admin' => 
      array (
        'class' => 'App\\Application\\Actions\\DashboardRenderAction',
        'auth' => true,
      ),
      '/history' => 
      array (
        'class' => 'App\\Application\\Actions\\HistoryRenderAction',
        'auth' => false,
      ),
      '/' => 
      array (
        'class' => 'App\\Application\\Actions\\PermitRenderAction',
        'auth' => false,
      ),
      '/profile' => 
      array (
        'class' => 'App\\Application\\Actions\\ProfileRenderAction',
        'auth' => true,
      ),
      '/api/process_mail_queue' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemProcessMailQueueAction',
        'auth' => false,
      ),
      '/users' => 
      array (
        'class' => 'App\\Application\\Actions\\UserManagementRenderAction',
        'auth' => true,
      ),
    ),
    'POST' => 
    array (
      '/admin_login' => 
      array (
        'class' => 'App\\Application\\Actions\\AdminLoginAction',
        'auth' => false,
      ),
      '/api/get_date_info' => 
      array (
        'class' => 'App\\Application\\Actions\\ApiGetDateInfoAction',
        'auth' => false,
      ),
      '/api/get_template_price' => 
      array (
        'class' => 'App\\Application\\Actions\\ApiGetTemplatePriceAction',
        'auth' => false,
      ),
      '/api/search_permits' => 
      array (
        'class' => 'App\\Application\\Actions\\ApiSearchPermitsAction',
        'auth' => true,
      ),
      '/bank_import_process' => 
      array (
        'class' => 'App\\Application\\Actions\\BankImportProcessAction',
        'auth' => true,
      ),
      '/api/capture' => 
      array (
        'class' => 'App\\Application\\Actions\\CapturePaymentAction',
        'auth' => false,
      ),
      '/api/create_order' => 
      array (
        'class' => 'App\\Application\\Actions\\CheckoutCreateOrderAction',
        'auth' => false,
      ),
      '/api/finalize_wire' => 
      array (
        'class' => 'App\\Application\\Actions\\CheckoutFinalizeWireAction',
        'auth' => false,
      ),
      '/' => 
      array (
        'class' => 'App\\Application\\Actions\\PermitSubmitAction',
        'auth' => false,
      ),
      '/change_own_password' => 
      array (
        'class' => 'App\\Application\\Actions\\ProfileUpdatePasswordAction',
        'auth' => false,
      ),
      '/change_own_username' => 
      array (
        'class' => 'App\\Application\\Actions\\ProfileUpdateUsernameAction',
        'auth' => false,
      ),
      '/delete_role' => 
      array (
        'class' => 'App\\Application\\Actions\\RoleDeleteAction',
        'auth' => true,
      ),
      '/rename_role' => 
      array (
        'class' => 'App\\Application\\Actions\\RoleRenameAction',
        'auth' => false,
      ),
      '/save_role' => 
      array (
        'class' => 'App\\Application\\Actions\\RoleSaveAction',
        'auth' => true,
      ),
      '/api/check_update' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemCheckUpdateAction',
        'auth' => true,
      ),
      '/clear_cache' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemClearCacheAction',
        'auth' => false,
      ),
      '/api/ping' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemExtendSessionAction',
        'auth' => false,
      ),
      '/finalize_update' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemFinalizeUpdateAction',
        'auth' => false,
      ),
      '/api/perform_update' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemPerformUpdateAction',
        'auth' => true,
      ),
      '/api/process_mail_queue' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemProcessMailQueueAction',
        'auth' => false,
      ),
      '/change_user_role' => 
      array (
        'class' => 'App\\Application\\Actions\\UserChangeRoleAction',
        'auth' => true,
      ),
      '/rename_user' => 
      array (
        'class' => 'App\\Application\\Actions\\UserRenameAction',
        'auth' => false,
      ),
      '/change_user_password' => 
      array (
        'class' => 'App\\Application\\Actions\\UserResetPasswordAction',
        'auth' => false,
      ),
      '/save_user' => 
      array (
        'class' => 'App\\Application\\Actions\\UserSaveAction',
        'auth' => true,
      ),
      '/activate_voucher' => 
      array (
        'class' => 'App\\Application\\Actions\\VoucherToggleAction',
        'auth' => true,
      ),
      '/deactivate_voucher' => 
      array (
        'class' => 'App\\Application\\Actions\\VoucherToggleAction',
        'auth' => true,
      ),
    ),
    'GET|POST' => 
    array (
      '/admin_logout' => 
      array (
        'class' => 'App\\Application\\Actions\\AdminLogoutAction',
        'auth' => false,
      ),
      '/bank_import_analyze' => 
      array (
        'class' => 'App\\Application\\Actions\\BankImportAnalyzeAction',
        'auth' => false,
      ),
      '/dashboard_export' => 
      array (
        'class' => 'App\\Application\\Actions\\DashboardExportAction',
        'auth' => false,
      ),
      '/filter_dashboard' => 
      array (
        'class' => 'App\\Application\\Actions\\DashboardFilterAction',
        'auth' => false,
      ),
      '/datenschutz' => 
      array (
        'class' => 'App\\Application\\Actions\\DatenschutzAction',
        'auth' => false,
      ),
      '/rename_group' => 
      array (
        'class' => 'App\\Application\\Actions\\GroupRenameAction',
        'auth' => false,
      ),
      '/upload_group_image' => 
      array (
        'class' => 'App\\Application\\Actions\\GroupUploadImageAction',
        'auth' => false,
      ),
      '/history_cancel_permit' => 
      array (
        'class' => 'App\\Application\\Actions\\HistoryCancelPermitAction',
        'auth' => false,
      ),
      '/history_logout' => 
      array (
        'class' => 'App\\Application\\Actions\\HistoryLogoutAction',
        'auth' => false,
      ),
      '/history_print' => 
      array (
        'class' => 'App\\Application\\Actions\\HistoryPrintAction',
        'auth' => false,
      ),
      '/history_request_link' => 
      array (
        'class' => 'App\\Application\\Actions\\HistoryRequestLinkAction',
        'auth' => false,
      ),
      '/history_submit_code' => 
      array (
        'class' => 'App\\Application\\Actions\\HistorySubmitCodeAction',
        'auth' => false,
      ),
      '/history_verify_token' => 
      array (
        'class' => 'App\\Application\\Actions\\HistoryVerifyTokenAction',
        'auth' => false,
      ),
      '/impressum' => 
      array (
        'class' => 'App\\Application\\Actions\\ImpressumAction',
        'auth' => false,
      ),
      '/create_manual' => 
      array (
        'class' => 'App\\Application\\Actions\\PermitCreateManualAction',
        'auth' => false,
      ),
      '/permit_edit' => 
      array (
        'class' => 'App\\Application\\Actions\\PermitEditAction',
        'auth' => false,
      ),
      '/mark_as_paid' => 
      array (
        'class' => 'App\\Application\\Actions\\PermitMarkAsPaidAction',
        'auth' => false,
      ),
      '/suspend_permit' => 
      array (
        'class' => 'App\\Application\\Actions\\PermitToggleSuspensionAction',
        'auth' => false,
      ),
      '/unsuspend_permit' => 
      array (
        'class' => 'App\\Application\\Actions\\PermitToggleSuspensionAction',
        'auth' => false,
      ),
      '/change_own_avatar' => 
      array (
        'class' => 'App\\Application\\Actions\\ProfileUploadAvatarAction',
        'auth' => false,
      ),
      '/success' => 
      array (
        'class' => 'App\\Application\\Actions\\SuccessAction',
        'auth' => false,
      ),
      '/anonymize_archive' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemAnonymizeArchiveAction',
        'auth' => false,
      ),
      '/changelog' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemChangelogAction',
        'auth' => false,
      ),
      '/create_backup' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemCreateBackupAction',
        'auth' => false,
      ),
      '/cron' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemCronAction',
        'auth' => false,
      ),
      '/force_update_check' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemForceUpdateCheckAction',
        'auth' => false,
      ),
      '/migrate_data' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemMigrateDataAction',
        'auth' => false,
      ),
      '/resend_mail' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemResendMailAction',
        'auth' => false,
      ),
      '/restore_data' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemRestoreDataAction',
        'auth' => false,
      ),
      '/run_update_migrations' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemRunUpdateMigrationsAction',
        'auth' => false,
      ),
      '/truncate_target' => 
      array (
        'class' => 'App\\Application\\Actions\\SystemTruncateTargetAction',
        'auth' => false,
      ),
      '/delete_user' => 
      array (
        'class' => 'App\\Application\\Actions\\UserDeleteAction',
        'auth' => false,
      ),
      '/upload_avatar' => 
      array (
        'class' => 'App\\Application\\Actions\\UserUploadAvatarAction',
        'auth' => false,
      ),
      '/verify_render' => 
      array (
        'class' => 'App\\Application\\Actions\\VerificationRenderAction',
        'auth' => false,
      ),
      '/verify_submit' => 
      array (
        'class' => 'App\\Application\\Actions\\VerificationSubmitAction',
        'auth' => false,
      ),
      '/create_voucher' => 
      array (
        'class' => 'App\\Application\\Actions\\VoucherCreateAction',
        'auth' => false,
      ),
      '/delete_voucher' => 
      array (
        'class' => 'App\\Application\\Actions\\VoucherDeleteAction',
        'auth' => false,
      ),
    ),
  ),
  'dynamic' => 
  array (
  ),
);