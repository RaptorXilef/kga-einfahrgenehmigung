<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Response\RedirectResponse;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\PermitService;

/**
 * Action für den "Daten korrigieren" Einstieg aus dem Checkout.
 * Lädt die temporären Daten und bereitet die Formular-Session vor.
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 */
final readonly class PermitEditAction implements ViewActionInterface
{
    public function __construct(private PermitService $permitService)
    {
    }

    public function execute(array $requestData): mixed
    {
        $get      = $requestData['get'];
        $tempData = $this->permitService->getVerifiedRequest((string) ($get['token'] ?? ''));
        if ($tempData !== null) {
            $_SESSION['form_data']      = $tempData;
            $_SESSION['verified_email'] = $tempData['email'];
            $_SESSION['edit_token']     = $get['token'];
        }

        return new RedirectResponse('index.php');
    }
}
