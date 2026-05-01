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

    public function handleRequest(array $get, array $post): void
    {
        // Logout-Logik
        if (isset($get['action']) && $get['action'] === 'logout') {
            unset($_SESSION['user_history_email']);
            \header('Location: history.php');

            return;
        }

        $message        = '';
        $emailInSession = $_SESSION['user_history_email'] ?? null;

        // 1. Login via E-Mail Formular
        if (isset($post['request_link'])) {
            $email   = \trim((string) ($post['email'] ?? ''));
            $permits = $this->permitService->getHistoryByEmail($email);

            if ($permits !== []) {
                $token = $this->magicLinkService->createToken($email);
                $link  = $this->config->getBaseUrl() . 'history.php?token=' . $token;

                $this->mailService->sendTemplate($email, 'Login: Ihre Genehmigungen', 'magic_link', [
                    'link'        => $link,
                    'duration'    => $this->config->get('magic_link_duration'),
                    'vereinsName' => $this->config->get('vereins_name'),
                ]);
                $message = 'Ein Login-Link wurde an Ihre E-Mail gesendet (gültig für '
                    . $this->config->get('magic_link_duration')
                    . ' Min).';
            } else {
                $message = 'Zu dieser E-Mail wurden keine Genehmigungen gefunden.';
            }
        }

        // 2. Verifizierung via Token aus URL
        if (isset($get['token'])) {
            $email = $this->magicLinkService->verifyToken((string) $get['token']);
            if ($email) {
                $_SESSION['user_history_email'] = $email;
                $emailInSession                 = $email;
            } else {
                $message = 'Der Link ist ungültig oder abgelaufen.';
            }
        }

        // 3. Anzeige
        if ($emailInSession) {
            $this->render('history_list', [
                'permits'  => $this->permitService->getHistoryByEmail($emailInSession),
                'email'    => $emailInSession,
                'settings' => $this->getSettingsArray(),
            ]);
        } else {
            $this->render('history_login', [
                'message'  => $message,
                'settings' => $this->getSettingsArray(),
            ]);
        }
    }

    private function getSettingsArray(): array
    {
        return [
            'vereins_name' => $this->config->get('vereins_name'),
            'jahresFarbe'  => $this->config->get('jahresFarbe'),
        ];
    }

    private function render(string $template, array $data): void
    {
        $appRoot = $this->config->get('root_path');
        \extract($data);
        include "{$appRoot}/templates/pages/{$template}.phtml";
    }
}
