<?php

declare(strict_types=1);

namespace App\Application\Actions;

use App\Application\Security\CsrfHelper;
use App\Contracts\Application\ViewActionInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
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
        private VerificationRepositoryInterface $verificationRepo,
    ) {
    }

    // TODO DOCBLOCK
    public function execute(array $requestData): void
    {
        $post = $requestData['post'];

        // Eingaben trimmen und HTML-Tags vorab entfernen (Sticky Forms)
        $_SESSION['form_data'] = \array_map(function ($value) {
            return \is_string($value) ? \trim(\strip_tags($value)) : $value;
        }, $post);

        if (! CsrfHelper::verify($post)) {
            $msg = 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
            \header('Location: index.php?msg=' . \urlencode($msg));
            exit;
        }

        try {
            // KORREKTUR-MODUS
            if (
                isset($_SESSION['verified_email'], $_SESSION['edit_token'])
                && \strtolower(\trim($post['email'] ?? '')) === \strtolower(\trim($_SESSION['verified_email']))
            ) {
                $token   = $_SESSION['edit_token'];
                $oldData = $this->permitService->getVerifiedRequest($token);

                if ($oldData !== null) {
                    $priceRelevantChanged = ($oldData['template_key'] ?? '') !== ($post['template_key'] ?? '')
                        || ($oldData['typ'] ?? '') !== ($post['typ'] ?? '')
                        || ($oldData['voucher'] ?? '') !== ($post['voucher'] ?? '');

                    if (! $priceRelevantChanged) {
                        // Nur Name/Kennzeichen geändert -> Alter Preis bleibt erhalten!
                        $merged           = \array_merge($oldData, $post);
                        $merged['preis']  = $oldData['preis'] ?? 0;
                        $merged['status'] = 'offen';

                        $allVerified         = $this->verificationRepo->loadVerified();
                        $allVerified[$token] = $merged;
                        $this->verificationRepo->saveVerified($allVerified);

                        unset($_SESSION['form_data'], $_SESSION['verified_email'], $_SESSION['edit_token']);
                        \header('Location: checkout.php?token=' . $token);
                        exit;
                    }

                    // Preisrelevante Änderung -> Alten Token löschen
                    $allVerified = $this->verificationRepo->loadVerified();
                    if (isset($allVerified[$token])) {
                        unset($allVerified[$token]);
                        $this->verificationRepo->saveVerified($allVerified);
                    }

                    // Neustart der Bestätigung nötig
                    $this->permitService->createPendingVerification($post);
                    unset($_SESSION['form_data'], $_SESSION['verified_email'], $_SESSION['edit_token']);

                    $msg = 'Sie haben die Vorlage oder den Fahrzeugtyp geändert. Zu Ihrer Sicherheit müssen Sie Ihre E-Mail kurz erneut bestätigen, da sich der Preis geändert hat.';
                    \header('Location: index.php?sent=1&msg=' . \urlencode($msg));
                    exit;
                }
            }

            // NORMALER DURCHLAUF (Neuer Antrag)
            $this->permitService->createPendingVerification($post);
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
