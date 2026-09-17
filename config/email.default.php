<?php

declare(strict_types=1);

return [
    'mail' => [
        'default' => 'smtp', // Erlaubt: 'smtp', 'oauth', 'graph'
        'test_mail_active' => false,
        'send_board_notification' => true,
        'recipients' => [
            'live' => 'vorstand@echte-domain.de',
            'test' => 'deine-private-mail@test.de',
        ],
        'transports' => [
            'smtp' => [
                'host' => 'smtp.dein-provider.de',
                'port' => 465,
                'user' => 'no-reply@deine-kga.de',
                'pass' => 'dein-passwort',
                'from' => 'no-reply@deine-kga.de',
            ],
            'oauth' => [
                'host' => 'smtp.office365.com',
                'port' => 587,
                'user' => 'vorstand@deine-kga.de',
                'from' => 'vorstand@deine-kga.de',
                'clientId' => 'DEINE_AZURE_CLIENT_ID',
                'clientSecret' => 'DEIN_AZURE_CLIENT_SECRET',
                'tenantId' => 'DEINE_AZURE_TENANT_ID',
            ],
            'graph' => [
                'from' => 'vorstand@deine-kga.de',
                'clientId' => 'DEINE_AZURE_CLIENT_ID',
                'clientSecret' => 'DEIN_AZURE_CLIENT_SECRET',
                'tenantId' => 'DEINE_AZURE_TENANT_ID',
            ],
        ],
    ],
    'mail_log_max_entries' => 5000,
    'mail_log_display_limit' => 250,
    'mail_queue_limit_web' => 3,
    'mail_queue_limit_admin' => 10,
    'mail_queue_limit_cron' => 50,
    'magic_link_duration' => 15,
    'hours_pending_verify' => 24,
    'hours_pending_finalize' => 48,
];
