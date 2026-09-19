<?php

declare(strict_types=1);

return [
    'mail' => [
        'default' => 'smtp', // Erlaubt: 'smtp', 'oauth', 'graph'
        'send_board_notification' => true,
        // Verteiler für Vorstands-Benachrichtigungen
        'board_recipients' => 'vorstand@echte-domain.de, finanzen@echte-domain.de',
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

    // --- SEPARATER TEST-TRANSPORT FÜR DIE SANDBOX ---
    // Wird automatisch von getMailSettings() geladen, wenn 'test_mode' aktiv ist
    'mail-test' => [
        'default' => 'smtp',
        'send_board_notification' => true,
        // E-Mails an den Vorstand gehen hierhin
        'board_recipients' => 'deine-private-mail@test.de',
        // Alle anderen E-Mails (Pächter, Tickets) werden zwingend auf diese Adresse umgeleitet (Sandbox-Trap)
        // selbst wenn eine andere Email beim Formular angegeben wird!
        'catch_all_recipient' => 'deine-private-mail@test.de',
        'transports' => [
            'smtp' => [
                'host' => 'smtp.mailtrap.io', // Beispiel für Mailcatcher
                'port' => 2525,
                'user' => 'test-user',
                'pass' => 'test-pass',
                'from' => 'sandbox@deine-kga.de',
            ],
            // oauth und graph können hier bei Bedarf auch eingefügt werden
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
