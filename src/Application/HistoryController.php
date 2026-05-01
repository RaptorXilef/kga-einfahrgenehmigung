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
use App\Core\Entity\Permit;
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
        // 1. Session & Logout prüfen (Early Return)
        if ($this->processLogout($get)) {
            return;
        }

        $emailInSession = (string) ($_SESSION['user_history_email'] ?? '');
        $message        = '';

        // 2. Druck-Aktion
        if (isset($get['action'], $get['code']) && $get['action'] === 'print') {
            $this->handlePrintAction((string) $get['code'], $emailInSession);

            return;
        }

        // 3. Login-Logik (Magic Link anfordern)
        if (isset($post['request_link'])) {
            $message = $this->handleLinkRequest((string) ($post['email'] ?? ''));
        }

        // 4. Token-Verifizierung (Begradigt ohne Else)
        if (isset($get['token'])) {
            $verifiedEmail = $this->magicLinkService->verifyToken((string) $get['token']);
            $message       = 'Der Link ist ungültig oder abgelaufen.';

            if ($verifiedEmail !== null) {
                $_SESSION['user_history_email'] = $verifiedEmail;
                $emailInSession                 = $verifiedEmail;
                $message                        = '';
            }
        }

        // 5. View-Auswahl
        $this->renderView($emailInSession, $message);
    }

    /**
     * @param array<string, mixed> $get
     */
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
        if ($emailInSession === '') {
            \header('Location: history.php');

            return;
        }

        $permit = $this->permitService->getStorage()->findByHash($code);
        if (! ($permit instanceof Permit) || \strtolower($permit->email) !== \strtolower($emailInSession)) {
            return;
        }

        $this->render('history_print_view', [
            'permit'   => $permit,
            'settings' => $this->getSettingsArray(),
            'appRoot'  => $this->config->get('root_path'),
        ]);
    }

    private function handleLinkRequest(string $email): string
    {
        $permits = $this->permitService->getHistoryByEmail($email);
        if ($permits === []) {
            return 'Zu dieser E-Mail wurden keine Genehmigungen gefunden.';
        }

        $token = $this->magicLinkService->createToken($email);
        $link  = $this->config->getBaseUrl() . 'history.php?token=' . $token;

        $this->mailService->sendTemplate($email, 'Login: Ihre Genehmigungen', 'magic_link', [
            'link'        => $link,
            'duration'    => $this->config->get('magic_link_duration'),
            'vereinsName' => $this->config->get('vereins_name'),
        ]);

        return 'Ein Login-Link wurde an Ihre E-Mail gesendet (gültig für '
            . $this->config->get('magic_link_duration')
            . ' Min).';
    }

    private function renderView(string $email, string $message): void
    {
        if ($email !== '') {
            $this->render('history_list', [
                'permits'  => $this->permitService->getHistoryByEmail($email),
                'email'    => $email,
                'settings' => $this->getSettingsArray(),
            ]);

            return;
        }

        $this->render('history_login', [
            'message'  => $message,
            'settings' => $this->getSettingsArray(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettingsArray(): array
    {
        return [
            'vereins_name'       => $this->config->get('vereins_name'),
            'jahresFarbe'        => $this->config->get('jahresFarbe'),
            'opening_hours'      => $this->config->get('opening_hours'),
            'terminkalender_url' => $this->config->get('terminkalender_url'),
            'base_url'           => $this->config->getBaseUrl(),
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
