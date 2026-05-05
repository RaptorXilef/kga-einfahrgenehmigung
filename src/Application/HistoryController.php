<?php

// SPDX-License-Identifier: CC BY-NC-SA 4.0

/**
 * Dieser Controller steuert die Anzeige der Historie.
 *
 * @file src/Application/HistoryController.php
 */

declare(strict_types=1);

namespace App\Application;

use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Core\Service\MagicLinkService;
use App\Core\Service\PermitService;

final readonly class HistoryController
{
    public function __construct(
        private ConfigInterface $config,
        private PermitService $permitService,
        private MagicLinkService $magicLinkService,
        private MailServiceInterface $mailService,
    ) {
    }

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     */
    public function handleRequest(array $get, array $post): void
    {
        // 1. Session & Logout prüfen
        if ($this->processLogout($get)) {
            return;
        }

        $emailInSession = (string) ($_SESSION['user_history_email'] ?? '');

        // --- A. POST-AKTION: Link/Code anfordern ---
        if (isset($post['request_link'])) {
            $email   = \trim((string) ($post['email'] ?? ''));
            $permits = $this->permitService->getHistoryByEmail($email);

            if ($permits === []) {
                $msg = 'Zu dieser E-Mail wurden keine Genehmigungen gefunden.';
                \header('Location: history.php?sent=0&msg=' . \urlencode($msg));
            } else {
                $data = $this->magicLinkService->createToken($email);
                $link = $this->config->getBaseUrl() . 'history.php?token=' . $data['token'];

                $this->mailService->sendTemplate($email, 'Login-Code: Ihre Genehmigungen', 'magic_link', [
                    'link'        => $link,
                    'code'        => $data['code'],
                    'duration'    => $this->config->get('magic_link_duration'),
                    'vereinsName' => $this->config->get('vereins_name'),
                ]);

                $msg = 'Code wurde gesendet (gültig für ' . $this->config->get('magic_link_duration') . ' Min).';
                \header('Location: history.php?sent=1&msg=' . \urlencode($msg));
            }
            exit;
        }

        // --- B. POST-AKTION: Manueller Code-Submit ---
        if (isset($post['submit_code'])) {
            $verifiedEmail = $this->magicLinkService->verifyAny((string) ($post['login_code'] ?? ''));
            if ($verifiedEmail) {
                $_SESSION['user_history_email'] = $verifiedEmail;
                \header('Location: history.php'); // Erfolgreich eingeloggt -> Saubere URL
                exit;
            }

            // Fehlerfall: Zurück zum Eingabefeld mit Fehlermeldung
            \header('Location: history.php?sent=1&msg=' . \urlencode('Der Code ist ungültig oder abgelaufen.'));
            exit;
        }

        // 2. Nachricht & Status aus der URL holen (für die Anzeige nach Redirects)
        $displayMessage = (string) ($get['msg'] ?? '');
        $isSuccess      = ($get['sent'] ?? '') === '1';

        // --- C. GET-AKTION: Token-Verifizierung (Klick auf E-Mail Link) ---
        if (isset($get['token'])) {
            $verifiedEmail = $this->magicLinkService->verifyAny((string) $get['token']);
            if ($verifiedEmail) {
                $_SESSION['user_history_email'] = $verifiedEmail;
                \header('Location: history.php'); // Redirect für saubere URL ohne Token
                exit;
            }
            $displayMessage = 'Der Link ist ungültig oder abgelaufen.';
            $isSuccess      = false;
        }

        // --- D. GET-AKTION: Druckvorschau ---
        if (isset($get['action'], $get['code']) && $get['action'] === 'print') {
            $this->handlePrintAction((string) $get['code'], $emailInSession);

            return;
        }

        // 3. View-Auswahl
        $this->renderView($emailInSession, $displayMessage, $get, $isSuccess);
    }

    // In renderView den neuen Parameter $isSuccess hinzufügen:
    private function renderView(string $email, string $message, array $get, bool $isSuccess = false): void
    {
        if ($email === '') {
            $this->render('history_login', [
                'message'   => $message,
                'isSuccess' => $isSuccess, // Weitergabe an das Template
                'settings'  => $this->getSettingsArray(),
            ]);

            return;
        }

        // 1. Aktuelle Daten laden
        $permits = $this->permitService->getHistoryByEmail($email);

        // 2. Archive laden, falls angefordert (?load_archive=2025)
        $loadedYear = (int) ($get['load_archive'] ?? 0);
        if ($loadedYear > 0) {
            $archivePath = $this->config->get('root_path') . "/storage/daten_{$loadedYear}.json";
            if (\file_exists($archivePath)) {
                $archiveData = \json_decode((string) \file_get_contents($archivePath), true) ?? [];
                // Filter für E-Mail im Archiv
                foreach ($archiveData as $item) {
                    if (\strtolower((string) $item['email']) === \strtolower($email)) {
                        $permits[] = $this->permitService->arrayToEntity($item);
                    }
                }
            }
        }

        $this->render('history_list', [
            'permits'            => $permits,
            'email'              => $email,
            'settings'           => $this->getSettingsArray(),
            'currentArchiveYear' => $loadedYear,
        ]);
    }

    private function processLogout(array $get): bool
    {
        if (isset($get['action']) && $get['action'] === 'logout') {
            unset($_SESSION['user_history_email']);
            \header('Location: history.php');

            return true;
        }

        return false;
    }

    private function handlePrintAction(string $code, string $emailInSession): void
    {
        $permit = $this->permitService->getStorage()->findByHash($code);
        if ($permit && \strtolower($permit->owner->email) === \strtolower($emailInSession)) {
            $this->render('history_print_view', [
                'permit'   => $permit,
                'settings' => $this->getSettingsArray(),
                'appRoot'  => $this->config->get('root_path'),
            ]);
        } else {
            \header('Location: history.php');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name'       => $this->config->get('vereins_name'),
            'jahresFarbe'        => $this->config->get('jahresFarbe'),
            'base_url'           => $this->config->getBaseUrl(),
            'terminkalender_url' => $this->config->get('terminkalender_url'),
            'opening_hours'      => $this->config->get('opening_hours'),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function render(string $template, array $data): void
    {
        $appRoot = (string) $this->config->get('root_path');
        \extract($data);
        include "{$appRoot}/templates/pages/{$template}.phtml";
    }
}
