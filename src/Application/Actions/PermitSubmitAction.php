<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\PermitSubmitRequest;
use App\Application\Exception\ValidationException;
use App\Contracts\Application\ViewActionInterface;
use App\Core\Service\PermitService;

/**
 * Action zur Verarbeitung des abgesendeten Antragsformulars (POST).
 *
 * Path: src/Application/Actions/PermitSubmitAction.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitSubmitAction implements ViewActionInterface
{
    public function __construct(
        private PermitService $permitService,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $requestData): void
    {
        try {
            $dto = PermitSubmitRequest::fromArray($requestData['post']);
        } catch (ValidationException $e) {
            \header('Location: index.php?msg=' . \urlencode($e->getMessage()));
            exit;
        }

        // Wir legen die gereinigten Daten sofort in der Session ab (für Formular-Neuaufbau bei Fehlern)
        $_SESSION['form_data'] = $dto->toArray();

        try {
            // KORREKTUR-MODUS: Logik wurde sauber an den Service übergeben!
            if (isset($_SESSION['verified_email'], $_SESSION['edit_token'])) {
                $result = $this->permitService->updateVerifiedRequest(
                    $_SESSION['edit_token'],
                    $_SESSION['verified_email'],
                    $dto->toArray(),
                );

                unset($_SESSION['form_data'], $_SESSION['verified_email'], $_SESSION['edit_token']);

                if ($result === 'redirect_checkout') {
                    \header('Location: checkout.php?token=' . $_SESSION['edit_token']);
                    exit;
                }

                $msg = 'Sie haben die Vorlage oder den Fahrzeugtyp geändert. Zu Ihrer Sicherheit müssen Sie Ihre E-Mail kurz erneut bestätigen, da sich der Preis geändert hat.';
                \header('Location: index.php?sent=1&msg=' . \urlencode($msg));
                exit;
            }

            // NORMALER DURCHLAUF (Neuer Antrag)
            $this->permitService->createPendingVerification($dto->toArray());
            unset($_SESSION['form_data'], $_SESSION['verified_email'], $_SESSION['edit_token']);

            \header('Location: index.php?sent=1');
            exit;
        } catch (\Exception $exception) {
            \error_log('Permit Creation Error: ' . $exception->getMessage());
            $msg = 'Ein technischer Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
            \header('Location: index.php?msg=' . \urlencode($msg));
            exit;
        }
    }
}
