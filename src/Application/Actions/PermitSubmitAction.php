<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\DTO\PermitSubmitRequest;
use App\Application\Exception\ValidationException;
use App\Application\Session\SessionManager;
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
        private SessionManager $sessionManager,
    ) {
    }

    public function execute(array $requestData): void
    {
        try {
            $dto = PermitSubmitRequest::fromArray($requestData['post']);
        } catch (ValidationException $e) {
            \header('Location: index.php?msg=' . \urlencode($e->getMessage()));
            exit;
        }

        $this->sessionManager->setFormData($dto->toArray());

        try {
            $verifiedEmail = $this->sessionManager->getVerifiedEmail();
            $editToken     = $this->sessionManager->getEditToken();

            if ($verifiedEmail !== null && $editToken !== null) {
                $result = $this->permitService->updateVerifiedRequest($editToken, $verifiedEmail, $dto->toArray());
                $this->sessionManager->clearFormData();
                $this->sessionManager->clearEditState();

                if ($result === 'redirect_checkout') {
                    \header('Location: checkout.php?token=' . $editToken);
                    exit;
                }

                $msg = 'Sie haben die Vorlage oder den Fahrzeugtyp geändert. Bitte E-Mail erneut bestätigen.';
                \header('Location: index.php?sent=1&msg=' . \urlencode($msg));
                exit;
            }

            // NORMALER DURCHLAUF (Neuer Antrag)
            $this->permitService->createPendingVerification($dto->toArray());
            $this->sessionManager->clearFormData();
            $this->sessionManager->clearEditState();
            \header('Location: index.php?sent=1');
            exit;
        } catch (\Exception $exception) {
            \error_log('Permit Creation Error: ' . $exception->getMessage());
            $msg = 'Ein technischer Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
            \header('Location: index.php?msg=' . \urlencode('Ein Fehler ist aufgetreten.'));
            exit;
        }
    }
}
