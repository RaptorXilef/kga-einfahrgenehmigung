<?php

declare(strict_types=1);

return [
    'exact' => [
        'POST' => [
            '/admin_login' => [
                'class' => 'App\\Application\\Actions\\AdminLoginAction',
                'auth' => false,
            ],
            '/bank_import_process' => [
                'class' => 'App\\Application\\Actions\\BankImportProcessAction',
                'auth' => true,
            ],
            '/finalize_wire' => [
                'class' => 'App\\Application\\Actions\\CheckoutFinalizeWireAction',
                'auth' => false,
            ],
            '/change_own_password' => [
                'class' => 'App\\Application\\Actions\\ProfileUpdatePasswordAction',
                'auth' => false,
            ],
            '/change_own_username' => [
                'class' => 'App\\Application\\Actions\\ProfileUpdateUsernameAction',
                'auth' => false,
            ],
            '/delete_role' => [
                'class' => 'App\\Application\\Actions\\RoleDeleteAction',
                'auth' => true,
            ],
            '/rename_role' => [
                'class' => 'App\\Application\\Actions\\RoleRenameAction',
                'auth' => false,
            ],
            '/save_role' => [
                'class' => 'App\\Application\\Actions\\RoleSaveAction',
                'auth' => true,
            ],
            '/clear_cache' => [
                'class' => 'App\\Application\\Actions\\SystemClearCacheAction',
                'auth' => false,
            ],
            '/finalize_update' => [
                'class' => 'App\\Application\\Actions\\SystemFinalizeUpdateAction',
                'auth' => false,
            ],
            '/change_user_role' => [
                'class' => 'App\\Application\\Actions\\UserChangeRoleAction',
                'auth' => true,
            ],
            '/rename_user' => [
                'class' => 'App\\Application\\Actions\\UserRenameAction',
                'auth' => false,
            ],
            '/change_user_password' => [
                'class' => 'App\\Application\\Actions\\UserResetPasswordAction',
                'auth' => false,
            ],
            '/save_user' => [
                'class' => 'App\\Application\\Actions\\UserSaveAction',
                'auth' => true,
            ],
            '/activate_voucher' => [
                'class' => 'App\\Application\\Actions\\VoucherToggleAction',
                'auth' => true,
            ],
            '/deactivate_voucher' => [
                'class' => 'App\\Application\\Actions\\VoucherToggleAction',
                'auth' => true,
            ],
        ],
        'GET|POST' => [
            '/admin_logout' => [
                'class' => 'App\\Application\\Actions\\AdminLogoutAction',
                'auth' => false,
            ],
            '/get_date_info' => [
                'class' => 'App\\Application\\Actions\\ApiGetDateInfoAction',
                'auth' => false,
            ],
            '/get_template_price' => [
                'class' => 'App\\Application\\Actions\\ApiGetTemplatePriceAction',
                'auth' => false,
            ],
            '/search_permits' => [
                'class' => 'App\\Application\\Actions\\ApiSearchPermitsAction',
                'auth' => false,
            ],
            '/bank_import_analyze' => [
                'class' => 'App\\Application\\Actions\\BankImportAnalyzeAction',
                'auth' => false,
            ],
            '/capture' => [
                'class' => 'App\\Application\\Actions\\CapturePaymentAction',
                'auth' => false,
            ],
            '/checkout' => [
                'class' => 'App\\Application\\Actions\\CheckoutAction',
                'auth' => false,
            ],
            '/create_order' => [
                'class' => 'App\\Application\\Actions\\CheckoutCreateOrderAction',
                'auth' => false,
            ],
            '/dashboard_export' => [
                'class' => 'App\\Application\\Actions\\DashboardExportAction',
                'auth' => false,
            ],
            '/filter_dashboard' => [
                'class' => 'App\\Application\\Actions\\DashboardFilterAction',
                'auth' => false,
            ],
            '/datenschutz' => [
                'class' => 'App\\Application\\Actions\\DatenschutzAction',
                'auth' => false,
            ],
            '/rename_group' => [
                'class' => 'App\\Application\\Actions\\GroupRenameAction',
                'auth' => false,
            ],
            '/upload_group_image' => [
                'class' => 'App\\Application\\Actions\\GroupUploadImageAction',
                'auth' => false,
            ],
            '/history_cancel_permit' => [
                'class' => 'App\\Application\\Actions\\HistoryCancelPermitAction',
                'auth' => false,
            ],
            '/history_logout' => [
                'class' => 'App\\Application\\Actions\\HistoryLogoutAction',
                'auth' => false,
            ],
            '/history_print' => [
                'class' => 'App\\Application\\Actions\\HistoryPrintAction',
                'auth' => false,
            ],
            '/history_render' => [
                'class' => 'App\\Application\\Actions\\HistoryRenderAction',
                'auth' => false,
            ],
            '/history_request_link' => [
                'class' => 'App\\Application\\Actions\\HistoryRequestLinkAction',
                'auth' => false,
            ],
            '/history_submit_code' => [
                'class' => 'App\\Application\\Actions\\HistorySubmitCodeAction',
                'auth' => false,
            ],
            '/history_verify_token' => [
                'class' => 'App\\Application\\Actions\\HistoryVerifyTokenAction',
                'auth' => false,
            ],
            '/impressum' => [
                'class' => 'App\\Application\\Actions\\ImpressumAction',
                'auth' => false,
            ],
            '/create_manual' => [
                'class' => 'App\\Application\\Actions\\PermitCreateManualAction',
                'auth' => false,
            ],
            '/permit_edit' => [
                'class' => 'App\\Application\\Actions\\PermitEditAction',
                'auth' => false,
            ],
            '/mark_as_paid' => [
                'class' => 'App\\Application\\Actions\\PermitMarkAsPaidAction',
                'auth' => false,
            ],
            '/permit_render' => [
                'class' => 'App\\Application\\Actions\\PermitRenderAction',
                'auth' => false,
            ],
            '/permit_submit' => [
                'class' => 'App\\Application\\Actions\\PermitSubmitAction',
                'auth' => false,
            ],
            '/suspend_permit' => [
                'class' => 'App\\Application\\Actions\\PermitToggleSuspensionAction',
                'auth' => false,
            ],
            '/unsuspend_permit' => [
                'class' => 'App\\Application\\Actions\\PermitToggleSuspensionAction',
                'auth' => false,
            ],
            '/change_own_avatar' => [
                'class' => 'App\\Application\\Actions\\ProfileUploadAvatarAction',
                'auth' => false,
            ],
            '/success' => [
                'class' => 'App\\Application\\Actions\\SuccessAction',
                'auth' => false,
            ],
            '/anonymize_archive' => [
                'class' => 'App\\Application\\Actions\\SystemAnonymizeArchiveAction',
                'auth' => false,
            ],
            '/changelog' => [
                'class' => 'App\\Application\\Actions\\SystemChangelogAction',
                'auth' => false,
            ],
            '/check_update' => [
                'class' => 'App\\Application\\Actions\\SystemCheckUpdateAction',
                'auth' => false,
            ],
            '/create_backup' => [
                'class' => 'App\\Application\\Actions\\SystemCreateBackupAction',
                'auth' => false,
            ],
            '/cron' => [
                'class' => 'App\\Application\\Actions\\SystemCronAction',
                'auth' => false,
            ],
            '/extend_session' => [
                'class' => 'App\\Application\\Actions\\SystemExtendSessionAction',
                'auth' => false,
            ],
            '/force_update_check' => [
                'class' => 'App\\Application\\Actions\\SystemForceUpdateCheckAction',
                'auth' => false,
            ],
            '/migrate_data' => [
                'class' => 'App\\Application\\Actions\\SystemMigrateDataAction',
                'auth' => false,
            ],
            '/perform_update' => [
                'class' => 'App\\Application\\Actions\\SystemPerformUpdateAction',
                'auth' => false,
            ],
            '/process_mail_queue' => [
                'class' => 'App\\Application\\Actions\\SystemProcessMailQueueAction',
                'auth' => false,
            ],
            '/resend_mail' => [
                'class' => 'App\\Application\\Actions\\SystemResendMailAction',
                'auth' => false,
            ],
            '/restore_data' => [
                'class' => 'App\\Application\\Actions\\SystemRestoreDataAction',
                'auth' => false,
            ],
            '/run_update_migrations' => [
                'class' => 'App\\Application\\Actions\\SystemRunUpdateMigrationsAction',
                'auth' => false,
            ],
            '/truncate_target' => [
                'class' => 'App\\Application\\Actions\\SystemTruncateTargetAction',
                'auth' => false,
            ],
            '/delete_user' => [
                'class' => 'App\\Application\\Actions\\UserDeleteAction',
                'auth' => false,
            ],
            '/upload_avatar' => [
                'class' => 'App\\Application\\Actions\\UserUploadAvatarAction',
                'auth' => false,
            ],
            '/verify_render' => [
                'class' => 'App\\Application\\Actions\\VerificationRenderAction',
                'auth' => false,
            ],
            '/verify_submit' => [
                'class' => 'App\\Application\\Actions\\VerificationSubmitAction',
                'auth' => false,
            ],
            '/create_voucher' => [
                'class' => 'App\\Application\\Actions\\VoucherCreateAction',
                'auth' => false,
            ],
            '/delete_voucher' => [
                'class' => 'App\\Application\\Actions\\VoucherDeleteAction',
                'auth' => false,
            ],
        ],
        'GET' => [
            '/admin_print' => [
                'class' => 'App\\Application\\Actions\\AdminPrintAction',
                'auth' => false,
            ],
            '/check_permit' => [
                'class' => 'App\\Application\\Actions\\CheckPermitAction',
                'auth' => false,
            ],
            '/render_dashboard' => [
                'class' => 'App\\Application\\Actions\\DashboardRenderAction',
                'auth' => true,
            ],
            '/render_profile' => [
                'class' => 'App\\Application\\Actions\\ProfileRenderAction',
                'auth' => true,
            ],
            '/render_users' => [
                'class' => 'App\\Application\\Actions\\UserManagementRenderAction',
                'auth' => true,
            ],
        ],
    ],
    'dynamic' => [
    ],
];
