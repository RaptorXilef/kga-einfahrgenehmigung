<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\PermitService;

/**
 * Action für den "Daten korrigieren" Einstieg aus dem Checkout.
 * Lädt die temporären Daten und bereitet die Formular-Session vor.
 *
 * Path: src/Application/Actions/PermitEditAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitEditAction implements ViewActionInterface
{
    public function __construct(private PermitService $permitService)
    {
    }

    // TODO DOCBLOCK
    public function execute(array $requestData): void
    {
        $get = $requestData['get'];

        $tempData = $this->permitService->getVerifiedRequest((string) ($get['token'] ?? ''));
        if ($tempData !== null) {
            $_SESSION['form_data']      = $tempData;
            $_SESSION['verified_email'] = $tempData['email']; // Wir merken uns: Diese E-Mail ist safe!
            $_SESSION['edit_token']     = $get['token'];
        }

        \header('Location: index.php');
        exit;
    }
}
