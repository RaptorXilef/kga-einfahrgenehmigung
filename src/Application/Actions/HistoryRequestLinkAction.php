<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Mail\MailServiceInterface;
use App\Contracts\Security\RateLimiterInterface;
use App\Core\Service\MagicLinkService;
use App\Core\Service\PermitService;

/**
 * TODO DOCBLOCK
 *
 * Path: src/Application/Actions/HistoryRequestLinkAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class HistoryRequestLinkAction implements ViewActionInterface
{
    public function __construct(
        private ConfigInterface $config,
        private MagicLinkService $magicLinkService,
        private MailServiceInterface $mailService,
        private PermitService $permitService,
        private RateLimiterInterface $rateLimiter,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $requestData): void
    {
        $post  = $requestData['post'];
        $ip    = $requestData['ip'];
        $email = \trim((string) ($post['email'] ?? ''));

        if ($this->rateLimiter->isBlocked($ip)) {
            \header('Location: history.php?sent=0&msg=' . \urlencode('Zu viele Anfragen. Bitte warten Sie 15 Minuten.'));
            exit;
        }

        $permits = $this->permitService->getHistoryByEmail($email);

        if ($permits === []) {
            $this->rateLimiter->recordFailedAttempt($ip);
        } else {
            $this->rateLimiter->clearAttempts($ip);
            $data = $this->magicLinkService->createToken($email);
            $link = $this->config->getBaseUrl() . 'history.php?token=' . $data['token'];

            $this->mailService->sendTemplate(
                $email,
                'Login-Code: Ihre Genehmigungen',
                'magic_link',
                [
                    'baseUrl'     => $this->config->getBaseUrl(),
                    'code'        => $data['code'],
                    'duration'    => $this->config->get('magic_link_duration'),
                    'link'        => $link,
                    'vereinsName' => $this->config->get('vereins_name'),
                ],
            );
        }

        $msg = 'Falls Genehmigungen zu dieser E-Mail existieren, wurde ein Code gesendet.';
        \header('Location: history.php?sent=1&msg=' . \urlencode($msg));
        exit;
    }
}
