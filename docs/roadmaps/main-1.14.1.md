# templates/<br>

├── components/                 <-- Ehemals "partials". Dinge, die ÜBERALL vorkommen können.<br>
│   ├── _test_mode_indicator.phtml<br>
│   ├──_header_nav.phtml       <-- Die Admin-Nav aus dem Dashboard<br>
│   └──_alert.phtml            <-- Für die $message Anzeige<br>
├── emails/                     <-- Bleibt flach, da meist simpel<br>
│   ├── board_notification.phtml<br>
│   ├── magic_link.phtml<br>
│   ├── payment_request.phtml<br>
│   ├── permit_a4_document.phtml<br>
│   ├── permit_issued.phtml<br>
│   └── verify_email.phtml<br>
└── pages/<br>
    ├── admin_dashboard.phtml   <-- Das neue "Skelett"<br>
    ├── admin_dashboard/        <-- PRIVATER Ordner für Dashboard-Bausteine<br>
    │   ├──_stats_cards.phtml<br>
    │   ├──_tab_active.phtml<br>
    │   ├──_tab_future.phtml<br>
    │   ├──_tab_expired.phtml<br>
    │   ├──_tab_vouchers.phtml<br>
    │   ├──_tab_tools.phtml<br>
    │   ├──_tab_logs.phtml<br>
    │   └──_sidebar_ranking.phtml<br>
    ├── check_public.phtml<br>
    ├── check_admin.phtml<br>
    ├── check/                  <-- PRIVATER Ordner für Check-Bausteine<br>
    │   └──_details.phtml      <-- Hierhin wandert _check_details.phtml<br>
    ├── history_list.phtml<br>
    ├── history_login.phtml<br>
    ├── history_print_view.phtml<br>
    ├── admin_users.phtml<br>
    ├── admin_login.phtml<br>
    ├── formular.phtml          <-- Das Hauptantragsformular<br>
    └── verify_error.phtml<br>
