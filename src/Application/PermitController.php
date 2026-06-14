<?php

// Path: src\Application\PermitController.php
declare(strict_types=1);

namespace App\Application;

use App\Application\View\TemplateRenderer;
use App\Contracts\Config\ConfigInterface;
use App\Contracts\Storage\VerificationRepositoryInterface;
use App\Contracts\Storage\VoucherRepositoryInterface;
use App\Core\Service\PermitService;
use App\Core\Service\VoucherService;

/**
 * Controller für den regulären, öffentlichen Genehmigungs-Beantragungsprozess.
 *
 * Verarbeitet die Formularübermittlung und leitet die E-Mail-Validierungsschleife ein.
 *
 * Path: src/Application/PermitController.php
 *
 * SPDX-License-Identifier: LicenseRef-Proprietary
 * Copyright (c) 2026 Felix Maywald alias RaptorXilef. All rights reserved.
 * Usage without explicit permission is strictly prohibited.
 * See LICENSE.md for full license details.
 */
final readonly class PermitController
{
    public function __construct(
        private ConfigInterface $config,
        private PermitService $permitService,
        private TemplateRenderer $renderer,
        private VerificationRepositoryInterface $verificationRepo, // Das Repository!
        private VoucherRepositoryInterface $voucherRepository,
        private VoucherService $voucherService,
    ) {
    }

    /**
     * Nimmt Antragsdaten entgegen oder zeigt die Erfolgsmeldung nach Erstübermittlung an.
     * Führt bei POST-Aktionen ein `createPendingVerification` aus und triggert Redirects.
     *
     * @param array<string, mixed> $post Entspricht $_POST
     * @param array<string, mixed> $get  Entspricht $_GET
     */
    public function handleRequest(array $post, array $get): void
    {
        if (\session_status() === \PHP_SESSION_NONE) {
            \session_start();
        }

        $message = '';
        $success = false;

        // Wenn der Nutzer vom Checkout auf "Daten korrigieren" klickt
        if (isset($get['edit'], $get['token'])) {
            $tempData = $this->permitService->getVerifiedRequest((string) $get['token']);
            if ($tempData !== null) {
                $_SESSION['form_data']      = $tempData;
                $_SESSION['verified_email'] = $tempData['email']; // Wir merken uns: Diese E-Mail ist safe!
                $_SESSION['edit_token']     = $get['token'];
            }
            \header('Location: index.php');
            exit;
        }

        // 1. Verarbeitung (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Formulardaten sicherheitshalber zwischenspeichern (Sticky Forms)
            // $_SESSION['form_data'] = $post;
            // Eingaben trimmen und HTML-Tags vorab entfernen
            $_SESSION['form_data'] = \array_map(function ($value) {
                return \is_string($value) ? \trim(\strip_tags($value)) : $value;
            }, $post);

            if (! \hash_equals($_SESSION['csrf_token'] ?? '', $post['csrf_token'] ?? '')) {
                $message = 'Fehler: Ungültiges Sicherheits-Token (CSRF). Bitte laden Sie die Seite neu.';
            } else {
                try {
                    // Prüfen, ob eine Korrektur vorliegt und die E-Mail NICHT geändert wurde
                    // KORREKTUR-MODUS
                    if (
                        isset($_SESSION['verified_email'], $_SESSION['edit_token'])
                        && \strtolower(\trim($post['email'] ?? '')) === \strtolower(\trim($_SESSION['verified_email']))
                    ) {

                        $token   = $_SESSION['edit_token'];
                        $oldData = $this->permitService->getVerifiedRequest($token);

                        if ($oldData !== null) {
                            // Prüfen ob sich preisrelevante Dinge geändert haben
                            $priceRelevantChanged = ($oldData['template_key'] ?? '') !== ($post['template_key'] ?? '')
                                || ($oldData['typ'] ?? '') !== ($post['typ'] ?? '')
                                || ($oldData['voucher'] ?? '') !== ($post['voucher'] ?? '');

                            if (! $priceRelevantChanged) {
                                // Nur Name/Kennzeichen geändert -> Alter Preis bleibt erhalten!
                                $merged           = \array_merge($oldData, $post);
                                $merged['preis']  = $oldData['preis'] ?? 0;
                                $merged['status'] = 'offen'; // Status für Checkout zurücksetzen

                                // Sauber über das VerificationRepository speichern!
                                $allVerified         = $this->verificationRepo->loadVerified();
                                $allVerified[$token] = $merged;
                                $this->verificationRepo->saveVerified($allVerified);

                                unset($_SESSION['form_data'], $_SESSION['verified_email'], $_SESSION['edit_token']);
                                \header('Location: checkout.php?token=' . $token);
                                exit;
                            }

                            // Preisrelevante Änderung -> Alten verifizierten Token rückstandslos löschen
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

                    // Bei Erfolg: Speicher leeren!
                    unset($_SESSION['form_data'], $_SESSION['verified_email'], $_SESSION['edit_token']);

                    \header('Location: index.php?sent=1');
                    exit;
                } catch (\Exception $exception) {
                    // Reale Fehler loggen, aber niemals dem Endnutzer im Klartext ausgeben!
                    \error_log('Permit Creation Error: ' . $exception->getMessage());
                    $message = 'Ein technischer Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.';
                }
            }
        }

        // Dynamische Bestätigungsnachricht abfangen
        if (isset($get['sent'])) {
            $success = true;
            $message = $get['msg'] ?? 'Bestätigung erforderlich! Wir haben Ihnen eine E-Mail gesendet. ' .
                'Bitte klicken Sie auf den Link darin, um Ihren Antrag zu aktivieren.';
        }

        // [x] sortiert
        // 3. View rendern (wie gehabt)
        $this->renderer->render('formular', [
            'agreements'        => $this->getParsedAgreements(),
            'formData'          => $_SESSION['form_data'] ?? [], // Ans Template übergeben
            'hasActiveVouchers' => $this->checkAvailableVouchers(), // Prüfen, ob einlösbare Gutscheine existieren
            'message'           => $message,
            'success'           => $success,
        ]);
    }

    /**
     * Prüft, ob mindestens ein Gutschein im System ist, der aktuell gültig ist.
     *
     * @return bool True, wenn mindestens ein einlösbarer Gutschein vorhanden ist.
     */
    private function checkAvailableVouchers(): bool
    {
        $vouchers = $this->voucherRepository->loadAll();

        foreach ($vouchers as $v) {
            if ($this->voucherService->isValid($v)) {
                return true; // Sobald einer gefunden wurde, reicht das für die Anzeige
            }
        }

        return false;
    }

    /**
     * Bereitet die Agreements aus der Config für die HTML-Ausgabe vor.
     * Löst Links (relativ/absolut) auf und ersetzt die [Tags] sicher durch HTML-Links.
     */
    private function getParsedAgreements(): array
    {
        $agreementsConfig = $this->config->get('agreements', []);
        $baseUrl          = $this->config->getBaseUrl() ?? '/';
        $parsed           = [];

        foreach ($agreementsConfig as $key => $agree) {
            $cleanLabel = \htmlspecialchars($agree['label']);

            if (! empty($agree['link'])) {
                if (\filter_var($agree['link'], \FILTER_VALIDATE_URL)) {
                    $finalLink = $agree['link'];
                } else {
                    $finalLink = \rtrim($baseUrl, '/') . '/' .
                        \ltrim($agree['link'], '/');
                }

                $linkHtml = '<a href="' . \htmlspecialchars($finalLink) .
                    '" target="_blank" style="color: var(--primary-color); text-decoration: underline; ' .
                    'font-weight: 500;">$1</a>';
                $renderedLabel = \preg_replace('/\[(.*?)\]/', $linkHtml, $cleanLabel);
            } else {
                $renderedLabel = \preg_replace('/\[(.*?)\]/', '$1', $cleanLabel);
            }

            // [x] sortiert
            $parsed[$key] = [
                'label_html' => $renderedLabel,
                'required'   => $agree['required'] ?? false,
            ];
        }

        return $parsed;
    }
}
